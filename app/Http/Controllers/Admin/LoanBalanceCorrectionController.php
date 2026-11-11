<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Accounting\JournalPostingException;
use App\Exceptions\Loans\LoanRepaymentException;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\Loans\LoanBalanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Menu "Perbaikan Saldo Pinjaman Anggota" — lihat LoanBalanceCorrectionService.
 * Hak akses sama dengan Perbaikan/Penyelarasan Jadwal: saldo_awal.update
 * (keputusan pemilik 11 Nov 2026).
 */
class LoanBalanceCorrectionController extends Controller
{
    public function __construct(private readonly LoanBalanceCorrectionService $perbaikan) {}

    public function index(Request $request): View
    {
        $this->authorize('saldo_awal.update');

        $cari = trim((string) $request->query('q', ''));

        return view('admin.pinjaman.perbaikan-saldo', [
            'cari' => $cari,
            'daftar' => $this->perbaikan->daftarBermasalah($cari === '' ? null : $cari),
        ]);
    }

    public function show(Request $request, Loan $loan): View
    {
        $this->authorize('saldo_awal.update');

        return view('admin.pinjaman.perbaikan-saldo-kartu', [
            'k' => $this->perbaikan->kartu($loan, $this->tanggalPer($request)),
            'pengguna' => User::query()->pluck('name', 'id'),
        ]);
    }

    /**
     * Formulir koreksi satu angsuran. Bila formulir sudah diisi (tombol
     * Pratinjau), koreksinya DICOBA di dalam transaksi database lalu
     * di-rollback — yang ditampilkan adalah kartu sesudah koreksi, tanpa
     * ada data yang tersimpan.
     */
    public function edit(Request $request, LoanRepayment $repayment): View
    {
        $this->authorize('saldo_awal.update');
        abort_if($repayment->migrated_at !== null, 404);

        $loan = $repayment->loan;
        $data = $request->has('pratinjau') ? $this->validasi($request, false) : null;
        $pratinjau = null;
        $galat = null;

        if ($data !== null) {
            DB::beginTransaction();
            try {
                $this->perbaikan->koreksi($repayment, $data, $request->user()->id);
                $pratinjau = $this->perbaikan->kartu(Loan::query()->withoutGlobalScopes()->findOrFail($loan->id), $this->tanggalPer($request));
            } catch (RuntimeException|LoanRepaymentException|JournalPostingException $e) {
                $galat = $e->getMessage();
            } finally {
                DB::rollBack();
            }
        }

        return view('admin.pinjaman.perbaikan-saldo-koreksi', [
            'r' => $repayment,
            'loan' => $loan,
            'sekarang' => $this->perbaikan->kartu($loan, $this->tanggalPer($request)),
            'data' => $data,
            'pratinjau' => $pratinjau,
            'galat' => $galat,
            'pengguna' => User::query()->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, LoanRepayment $repayment): RedirectResponse
    {
        $this->authorize('saldo_awal.update');
        abort_if($repayment->migrated_at !== null, 404);

        $data = $this->validasi($request, true);

        try {
            $baru = $this->perbaikan->koreksi($repayment, $data, $request->user()->id);
        } catch (RuntimeException|LoanRepaymentException|JournalPostingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Koreksi gagal dan dibatalkan seluruhnya: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.pinjaman.perbaikan-saldo.show', ['loan' => $repayment->loan_id, 'per' => $request->input('per')])
            ->with('status', "Angsuran #{$repayment->id} dibatalkan dan diganti angsuran #{$baru->id} "
                .'(tgl '.$baru->transaction_date->translatedFormat('d M Y').', Rp '.number_format((float) $baru->amount, 0, ',', '.')
                .'). Jurnal lama dibalik, jurnal baru diposting, jadwal & status pinjaman diselaraskan.');
    }

    public function selaraskan(Request $request, Loan $loan): RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        try {
            $hasil = $this->perbaikan->selaraskanBilaPerlu($loan, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $hasil === null
            ? 'Jadwal & status pinjaman ini sudah selaras dengan buku besar — tidak ada yang diubah.'
            : 'Jadwal & status pinjaman diselaraskan dengan buku besar ('.$hasil->rows_changed.' baris jadwal diubah). Bisa dibatalkan dari menu Penyelarasan Jadwal Angsuran.');
    }

    /**
     * @return array{tanggal: string, pokok: float, jasa: float, denda: float, alasan: string}
     */
    private function validasi(Request $request, bool $wajibKonfirmasi): array
    {
        $aturan = [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'pokok' => ['required', 'numeric', 'min:0'],
            'jasa' => ['required', 'numeric', 'min:0'],
            'denda' => ['nullable', 'numeric', 'min:0'],
            'alasan' => ['required', 'string', 'max:200'],
        ];
        if ($wajibKonfirmasi) {
            $aturan['konfirmasi'] = ['accepted'];
        }

        $v = $request->validate($aturan, [
            'tanggal.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
            'alasan.required' => 'Alasan koreksi wajib diisi (tercatat di jejak audit).',
            'konfirmasi.accepted' => 'Centang pernyataan persetujuan sebelum menyimpan koreksi.',
        ]);

        return [
            'tanggal' => $v['tanggal'],
            'pokok' => (float) $v['pokok'],
            'jasa' => (float) $v['jasa'],
            'denda' => (float) ($v['denda'] ?? 0),
            'alasan' => $v['alasan'],
        ];
    }

    private function tanggalPer(Request $request): ?Carbon
    {
        $per = $request->query('per', $request->input('per'));

        try {
            return $per ? Carbon::parse($per) : null;
        } catch (Throwable) {
            return null;
        }
    }
}

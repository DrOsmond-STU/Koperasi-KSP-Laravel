<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Accounting\JournalPostingException;
use App\Http\Controllers\Controller;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCorrection;
use App\Services\OpeningBalance\OpeningBalanceNeracaCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Menu "Koreksi Saldo Awal Neraca" — lihat OpeningBalanceNeracaCorrectionService.
 * Saldo awal migrasi & jurnal pembukaan tidak diubah; koreksi diposting
 * sebagai jurnal baru bertanggal cut-off. Hak akses: saldo_awal.update.
 */
class OpeningBalanceNeracaCorrectionController extends Controller
{
    public function __construct(private readonly OpeningBalanceNeracaCorrectionService $koreksi) {}

    /** Pilih batch (langsung dibuka bila hanya ada satu). */
    public function pilih(): View|RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        $batches = OpeningBalanceBatch::query()->with('branch')->orderBy('cutoff_date')->get();
        if ($batches->count() === 1) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batches->first());
        }

        return view('admin.saldo-awal.koreksi-neraca-pilih', ['batches' => $batches]);
    }

    public function index(OpeningBalanceBatch $batch): View
    {
        $this->authorize('saldo_awal.update');

        return view('admin.saldo-awal.koreksi-neraca', [
            'batch' => $batch->load('branch'),
            'posisi' => $this->koreksi->posisi($batch),
            'riwayat' => $this->koreksi->riwayat($batch),
            'kodeShu' => $this->koreksi->kodeAkunShu(),
        ]);
    }

    /** Pratinjau: dihitung saja, tidak ada yang disimpan. */
    public function pratinjau(Request $request, OpeningBalanceBatch $batch): View|RedirectResponse
    {
        $this->authorize('saldo_awal.update');
        [$target, $penyeimbang, $alasan] = $this->isian($request);

        try {
            $rencana = $this->koreksi->rencana($batch, $target, $penyeimbang);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->withInput()->with('error', $e->getMessage());
        }

        if ($rencana['baris'] === []) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->withInput()->with('error', 'Tidak ada saldo awal yang berubah — isi kolom "Saldo awal yang benar" pada akun yang ingin dikoreksi.');
        }

        return view('admin.saldo-awal.koreksi-neraca-pratinjau', [
            'batch' => $batch->load('branch'),
            'rencana' => $rencana,
            'target' => collect($rencana['baris'])->reject(fn ($b) => $b['penyeimbang'])
                ->mapWithKeys(fn ($b) => [$b['account']->id => number_format($b['menjadi'], 2, '.', '')])->all(),
            'penyeimbang' => $penyeimbang,
            'alasan' => $alasan,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function simpan(Request $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->authorize('saldo_awal.update');
        // Gagal validasi kembali ke formulir (bukan back(): halaman
        // sebelumnya adalah pratinjau yang hanya menerima POST).
        $v = Validator::make($request->all(), [
            'alasan' => ['required', 'string', 'max:200'],
            'konfirmasi' => ['accepted'],
            'password' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'uuid'],
        ], [
            'alasan.required' => 'Alasan koreksi wajib diisi (tercatat di jejak audit).',
            'konfirmasi.accepted' => 'Centang pernyataan persetujuan sebelum menyimpan.',
            'password.required' => 'Masukkan kata sandi Anda untuk menyimpan koreksi.',
        ]);
        if ($v->fails()) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->withErrors($v)->withInput($request->except('password'));
        }
        [$target, $penyeimbang, $alasan] = $this->isian($request);

        if (! Hash::check((string) $request->input('password'), $request->user()->password)) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->withInput($request->except('password'))
                ->with('error', 'Kata sandi salah — koreksi tidak disimpan.');
        }

        try {
            $hasil = $this->koreksi->simpan($batch, $target, $penyeimbang, $alasan, $request->user(), $request->input('idempotency_key'));
        } catch (RuntimeException|JournalPostingException $e) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->withInput($request->except('password'))
                ->with('error', 'Koreksi tidak disimpan: '.$e->getMessage());
        }

        return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)
            ->with('status', "Koreksi saldo awal #{$hasil->id} disimpan — jurnal koreksi #{$hasil->journal_entry_id} bertanggal "
                .$batch->cutoff_date->translatedFormat('d M Y').'. Saldo awal migrasi & jurnal pembukaan tidak diubah.');
    }

    public function batalkan(Request $request, OpeningBalanceBatch $batch, OpeningBalanceCorrection $correction): RedirectResponse
    {
        $this->authorize('saldo_awal.update');
        abort_unless((int) $correction->opening_balance_batch_id === (int) $batch->id, 404);
        $request->validate([
            'alasan_batal' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string'],
        ], [
            'alasan_batal.required' => 'Alasan pembatalan wajib diisi.',
            'password.required' => 'Masukkan kata sandi Anda untuk membatalkan koreksi.',
        ]);

        if (! Hash::check((string) $request->input('password'), $request->user()->password)) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->with('error', 'Kata sandi salah — koreksi tidak dibatalkan.');
        }

        try {
            $hasil = $this->koreksi->batalkan($correction, (string) $request->input('alasan_batal'), $request->user());
        } catch (RuntimeException|JournalPostingException $e) {
            return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.saldo-awal.koreksi-neraca', $batch)->with('status', "Koreksi saldo awal #{$hasil->id} dibatalkan — jurnalnya dibalik dengan jurnal #{$hasil->reversal_journal_entry_id}.");
    }

    /**
     * @return array{0: array<int, string>, 1: ?int, 2: string}
     */
    private function isian(Request $request): array
    {
        $target = [];
        foreach ((array) $request->input('target', []) as $accountId => $nilai) {
            $angka = self::angka($nilai);
            if ($angka !== null) {
                $target[(int) $accountId] = $angka;
            }
        }

        return [$target, $request->integer('penyeimbang') ?: null, trim((string) $request->input('alasan', ''))];
    }

    /**
     * "1.250.000", "1.250.000,50", "1250000.5", "Rp -30.000" → string angka;
     * kosong → null. Teks yang bukan angka dikembalikan apa adanya supaya
     * ditolak service dengan pesan yang jelas.
     */
    public static function angka(mixed $nilai): ?string
    {
        $s = str_replace([' ', 'Rp', 'rp', "\u{00A0}"], '', trim((string) $nilai));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        } elseif (substr_count($s, '.') > 1 || preg_match('/^-?\d{1,3}\.\d{3}$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return $s;
    }
}

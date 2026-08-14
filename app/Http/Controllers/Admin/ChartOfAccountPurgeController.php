<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurgeChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountPurgeService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Hapus massal Bagan Akun — satu layar untuk membersihkan akun yang salah
 * import atau akun bawaan yang tidak dipakai koperasi ini, tanpa menekan
 * tombol Hapus 200 kali di layar daftar.
 */
class ChartOfAccountPurgeController extends Controller
{
    /** Penanda yang ditulis ChartOfAccountImportService pada akun hasil migrasi. */
    private const PENANDA_MIGRASI = 'Migrasi SMIK%';

    public function __construct(private readonly ChartOfAccountPurgeService $purge) {}

    public function form(Request $request): View
    {
        $this->authorize('chart_of_account.delete');

        $sumber = in_array($request->query('sumber'), ['migrasi', 'bawaan'], true)
            ? $request->query('sumber')
            : 'semua';
        $awalan = trim((string) $request->query('awalan', ''));

        $query = ChartOfAccount::query();

        if ($sumber === 'migrasi') {
            $query->where('notes', 'like', self::PENANDA_MIGRASI);
        } elseif ($sumber === 'bawaan') {
            $query->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', self::PENANDA_MIGRASI));
        }

        if ($awalan !== '') {
            $query->where('code', 'like', $awalan.'%');
        }

        $accounts = $query->orderBy('code')->get();
        $blockers = $this->purge->blockers();

        return view('admin.master.bagan-akun.hapus-massal', [
            'accounts' => $accounts,
            'blockers' => $blockers,
            'childCounts' => $this->purge->childCounts(),
            'jumlahTotal' => ChartOfAccount::query()->count(),
            // Dihitung di sini supaya layar bisa menjelaskan sendiri kenapa
            // tidak ada yang bisa dicentang, alih-alih menyisakan tombol mati
            // tanpa keterangan.
            'jumlahTerkunci' => $accounts->filter(fn ($a) => isset($blockers[$a->id]))->count(),
            'sumber' => $sumber,
            'awalan' => $awalan,
        ]);
    }

    public function destroy(PurgeChartOfAccountRequest $request): RedirectResponse
    {
        $hasil = $this->purge->purge($request->validated()['ids']);

        $pesan = $hasil['dihapus'] > 0
            ? $hasil['dihapus'].' akun berhasil dihapus.'
            : 'Tidak ada akun yang terhapus.';

        return redirect()
            ->route('admin.master.chart-of-accounts.purge.form', $request->only('sumber', 'awalan'))
            ->with($hasil['dihapus'] > 0 ? 'status' : 'error', $pesan)
            ->with('purge_gagal', $hasil['gagal']);
    }
}

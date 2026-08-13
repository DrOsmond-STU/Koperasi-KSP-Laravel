<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpeningBalanceBatch;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Koreksi Data Saldo Awal — pengosongan massal per sub-modul sebelum batch
 * dikunci.
 *
 * Halaman show batch hanya menyediakan hapus satu baris; untuk membetulkan
 * import yang salah (mis. 1.224 baris simpanan) itu tidak praktis. Semua
 * aksi di sini HANYA berlaku pada batch berstatus draft — setelah dikunci,
 * jurnal pembukaan sudah terbit dan koreksi wajib lewat jurnal penyesuaian
 * (RUNBOOK §5.6), bukan dengan mengutak-atik baris saldo awal.
 */
class OpeningBalanceCorrectionController extends Controller
{
    /**
     * Urutan penting: installments dihapus lebih dulu saat sub-modul loans
     * dikosongkan, karena baris angsuran menunjuk opening_balance_loans.
     */
    private const SUB_MODULES = [
        'savings' => 'Simpanan',
        'loans' => 'Pinjaman',
        'installments' => 'Angsuran',
        'coa' => 'COA',
        'upf' => 'UPF',
        'stock' => 'Persediaan',
    ];

    public function index(OpeningBalanceBatch $batch): View
    {
        $this->authorize('saldo_awal.read');

        return view('admin.saldo-awal.koreksi', [
            'batch' => $batch,
            'subModules' => self::SUB_MODULES,
            'counts' => collect(self::SUB_MODULES)
                ->map(fn (string $label, string $key) => $this->relation($batch, $key)->count())
                ->all(),
        ]);
    }

    public function clear(OpeningBalanceBatch $batch, string $subModule): RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        if (! isset(self::SUB_MODULES[$subModule])) {
            abort(404);
        }

        if (! $batch->isDraft()) {
            return redirect()
                ->route('admin.saldo-awal.koreksi', $batch)
                ->with('error', 'Batch sudah terkunci — koreksi harus lewat jurnal penyesuaian, bukan dengan mengubah saldo awal.');
        }

        $deleted = DB::transaction(function () use ($batch, $subModule) {
            // Angsuran menunjuk opening_balance_loans; kalau pinjaman dihapus
            // lebih dulu, baris angsuran jadi yatim / melanggar constraint.
            if ($subModule === 'loans') {
                $batch->installments()->delete();
            }

            return $this->relation($batch, $subModule)->delete();
        });

        $label = self::SUB_MODULES[$subModule];
        $pesan = "Data Saldo Awal {$label} dikosongkan — {$deleted} baris dihapus.";

        if ($subModule === 'loans') {
            $pesan .= ' Baris Angsuran ikut dikosongkan karena menempel pada pinjaman.';
        }

        return redirect()
            ->route('admin.saldo-awal.koreksi', $batch)
            ->with('status', $pesan);
    }

    private function relation(OpeningBalanceBatch $batch, string $subModule): HasMany
    {
        return match ($subModule) {
            'savings' => $batch->savings(),
            'loans' => $batch->loans(),
            'installments' => $batch->installments(),
            'coa' => $batch->coaLines(),
            'upf' => $batch->upf(),
            'stock' => $batch->stock(),
        };
    }
}

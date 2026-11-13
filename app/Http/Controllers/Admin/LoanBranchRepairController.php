<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyLoanBranchRepairRequest;
use App\Models\Branch;
use App\Models\LoanBranchRepair;
use App\Services\Loans\LoanBranchRepairService;
use App\Services\Settings\CashSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Layar "Perbaikan Cabang Pinjaman".
 *
 * Angsuran dan jurnalnya bisa tercatat di cabang yang salah karena pinjaman
 * POS "hutang" mewarisi cabang penjualannya. Selama toko melayani kredit di
 * cabang lain, barisnya akan terus mendarat di sana — jadi ini bukan
 * perbaikan sekali jalan, melainkan pemeliharaan yang bisa diulang.
 *
 * Layarnya menampilkan pratinjau lebih dulu (berapa baris, dari cabang
 * mana, dan berapa pendapatan yang tertinggal) sebelum ada tombol yang
 * bisa ditekan, dan setiap pemindahan bisa dibatalkan dari riwayat.
 */
class LoanBranchRepairController extends Controller
{
    public function __construct(
        private readonly LoanBranchRepairService $repair,
        private readonly CashSettingsService $cashSettings,
    ) {}

    public function form(Request $request): View
    {
        $this->authorize('master_data.update');

        $cabang = Branch::query()->where('is_active', true)->orderBy('name')->get();

        // Bawaannya cabang pinjaman yang sudah disetel pengurus di Pengaturan
        // → Kas & Cabang; itu memang cabang yang seharusnya memiliki seluruh
        // kegiatan pinjaman, jadi menebaknya di sini menghemat satu langkah.
        $terpilih = $request->integer('branch_id')
            ?: ($this->cashSettings->loanBranchId() ?? $cabang->first()?->id);

        return view('admin.pinjaman.perbaikan-cabang', [
            'daftarCabang' => $cabang,
            'terpilih' => $terpilih,
            'pratinjau' => $terpilih ? $this->repair->pratinjau((int) $terpilih) : null,
            'riwayat' => LoanBranchRepair::query()
                ->with(['targetBranch', 'performedBy', 'revertedBy'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function apply(ApplyLoanBranchRepairRequest $request): RedirectResponse
    {
        try {
            $hasil = $this->repair->jalankan(
                (int) $request->validated('branch_id'),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return redirect()->route('admin.pinjaman.perbaikan-cabang.form', ['branch_id' => $request->validated('branch_id')])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pinjaman.perbaikan-cabang.form', ['branch_id' => $hasil->target_branch_id])
            ->with('status', sprintf(
                '%s baris dipindahkan ke %s — %d pinjaman, %d angsuran, %d jurnal. Pembukuan tetap seimbang.',
                number_format($hasil->totalMoved(), 0, ',', '.'),
                $hasil->targetBranch->name,
                $hasil->loans_moved,
                $hasil->repayments_moved,
                $hasil->entries_moved,
            ));
    }

    public function undo(Request $request, LoanBranchRepair $repair): RedirectResponse
    {
        $this->authorize('master_data.update');

        try {
            $this->repair->batalkan($repair, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.pinjaman.perbaikan-cabang.form')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.pinjaman.perbaikan-cabang.form')
            ->with('status', 'Perbaikan dibatalkan — setiap baris dikembalikan ke cabang asalnya.');
    }
}

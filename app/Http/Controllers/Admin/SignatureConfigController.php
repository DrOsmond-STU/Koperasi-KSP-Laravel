<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentSignatoryRequest;
use App\Http\Requests\StoreSignatureSlotRequest;
use App\Http\Requests\UpdateSignatureSlotRequest;
use App\Models\DocumentSignatory;
use App\Models\DocumentSignatureSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Setup Tanda Tangan" — daftar penanda tangan (nama+jabatan) TIDAK diikat
 * ke akun login/RBAC (Pengurus/Ketua Koperasi seringkali tidak punya akun
 * di sistem ini — mereka tanda tangan di kertas, bukan approve digital),
 * plus slot tanda tangan per kelompok dokumen yang bisa diisi salah satu
 * nama dari daftar itu atau dibiarkan kosong (cetakan tetap menampilkan
 * label+garis kosong untuk tanda tangan basah).
 */
class SignatureConfigController extends Controller
{
    /** Label default saat sebuah document_group belum punya slot sama sekali. */
    private const DEFAULT_SLOTS = [
        'pengajuan_pinjaman' => ['Diperiksa oleh (Pengurus)', 'Disetujui oleh (Ketua Koperasi)'],
        'pengajuan_penarikan' => ['Diperiksa oleh (Pengurus)', 'Disetujui oleh (Ketua Koperasi)'],
        'kas_keluar' => ['Pengurus/Ketua Koperasi'],
        'kas_masuk' => ['Pengurus/Ketua Koperasi'],
        'jurnal_umum' => ['Pengurus'],
        'dokumen_gudang' => ['Diketahui/Disetujui oleh (Pengurus)'],
        'aktiva_tetap' => ['Diketahui/Disetujui oleh (Pengurus)'],
        'laporan_kas_bank' => ['Pengurus'],
        'laporan_upf' => ['Pengurus'],
        'laporan_keuangan' => ['Disusun oleh (Bendahara)', 'Diperiksa oleh (Pengurus)', 'Disetujui oleh (Ketua Koperasi)'],
        'unit_usaha' => ['Diketahui oleh (Pengurus)'],
    ];

    public function index(): View
    {
        $this->authorize('cetakan.manage');

        $this->ensureDefaultSlotsExist();

        return view('admin.pengaturan.tanda-tangan', [
            'signatories' => DocumentSignatory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'slotsByGroup' => DocumentSignatureSlot::query()
                ->with('signatory')
                ->orderBy('slot_order')
                ->get()
                ->groupBy('document_group'),
            'documentGroups' => array_keys(self::DEFAULT_SLOTS),
        ]);
    }

    public function storeSignatory(StoreDocumentSignatoryRequest $request): RedirectResponse
    {
        DocumentSignatory::query()->create($request->validated());

        return back()->with('status', 'Penanda tangan berhasil ditambahkan.');
    }

    public function updateSignatory(StoreDocumentSignatoryRequest $request, DocumentSignatory $signatory): RedirectResponse
    {
        $signatory->update($request->validated());

        return back()->with('status', 'Penanda tangan berhasil diperbarui.');
    }

    public function destroySignatory(DocumentSignatory $signatory): RedirectResponse
    {
        $this->authorize('cetakan.manage');

        $signatory->delete();

        return back()->with('status', 'Penanda tangan berhasil dihapus.');
    }

    public function storeSlot(StoreSignatureSlotRequest $request): RedirectResponse
    {
        DocumentSignatureSlot::query()->create([
            ...$request->validated(),
            'slot_order' => DocumentSignatureSlot::query()->where('document_group', $request->validated('document_group'))->max('slot_order') + 1,
        ]);

        return back()->with('status', 'Slot tanda tangan berhasil ditambahkan.');
    }

    public function updateSlot(UpdateSignatureSlotRequest $request, DocumentSignatureSlot $slot): RedirectResponse
    {
        $slot->update($request->validated());

        return back()->with('status', 'Slot tanda tangan berhasil diperbarui.');
    }

    public function destroySlot(DocumentSignatureSlot $slot): RedirectResponse
    {
        $this->authorize('cetakan.manage');

        $slot->delete();

        return back()->with('status', 'Slot tanda tangan berhasil dihapus.');
    }

    private function ensureDefaultSlotsExist(): void
    {
        foreach (self::DEFAULT_SLOTS as $group => $labels) {
            if (DocumentSignatureSlot::query()->where('document_group', $group)->exists()) {
                continue;
            }

            foreach ($labels as $order => $label) {
                DocumentSignatureSlot::query()->create([
                    'document_group' => $group,
                    'slot_order' => $order,
                    'label' => $label,
                ]);
            }
        }
    }
}

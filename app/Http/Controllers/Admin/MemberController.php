<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    private const PHOTO_DISK = 'public';

    private const PHOTO_DIRECTORY = 'members';

    public function index(Request $request): View
    {
        $this->authorize('master_data.read');

        $search = $request->string('q')->trim()->value();

        return view('admin.anggota.index', [
            'members' => Member::query()
                ->with(['memberType', 'branch'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('member_number', 'like', "%{$search}%");
                }))
                ->latest()
                ->get(),
            'search' => $search,
            'memberTypes' => MemberType::query()->orderBy('name')->get(),
            'branches' => $this->availableBranches(),
        ]);
    }

    /**
     * Cetak Daftar Anggota (PDF) — semua/per jenis/per cabang. Pola sama
     * dengan MemberCardController::downloadPdf() (DomPDF langsung, bukan
     * lewat Report Builder umum karena filternya sederhana & tetap).
     */
    public function printList(Request $request): Response
    {
        $this->authorize('master_data.read');

        // dompdf membangun SELURUH dokumen sebagai objek frame di memori dan
        // biayanya tumbuh linier terhadap jumlah baris. Diukur pada PHP 8.3
        // (MemberPrintListTest): ~0,47 MB per baris, jadi 900 anggota butuh
        // ~420 MB di atas biaya boot. Dengan memory_limit 128M bawaan hosting,
        // request mati sebagai PHP fatal error — bukan exception, sehingga
        // tidak tercatat di log Laravel dan pengguna hanya melihat halaman
        // gagal tanpa pesan. LVE PMEM akun = 1G, jadi 768M masih aman.
        ini_set('memory_limit', '768M');

        $memberTypeId = $request->integer('member_type_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;

        $allowedBranchIds = $request->user()->allowedBranchIds();
        if ($branchId !== null && $allowedBranchIds !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        $query = Member::query()
            ->when($memberTypeId, fn ($q) => $q->where('member_type_id', $memberTypeId))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        // Batas keras: menaikkan memory_limit hanya menggeser titik gagalnya,
        // tidak menghapusnya. Di atas ambang ini cetakan ditolak dengan pesan
        // yang bisa ditindaklanjuti, bukan dibiarkan mati sebagai fatal error.
        $maxRows = (int) config('koperasi.cetak_daftar_anggota_maks_baris', 1400);
        $total = (clone $query)->count();

        if ($total > $maxRows) {
            return redirect()
                ->route('admin.members.index')
                ->with('error', "Daftar {$total} anggota terlalu besar untuk dicetak sekaligus (batas {$maxRows}). Silakan cetak per cabang atau per jenis anggota lewat filter di halaman ini.");
        }

        // Hanya kolom yang benar-benar dipakai daftar-pdf.blade.php. Selain
        // memangkas memori, ini menghindari menghidrasi kolom PII terenkripsi
        // (nik/address/phone/email/date_of_birth) yang ciphertext-nya panjang
        // dan sama sekali tidak ditampilkan di cetakan ini.
        $members = $query
            ->select(['id', 'branch_id', 'member_type_id', 'member_number', 'name', 'status', 'joined_at'])
            ->with(['memberType:id,name', 'branch:id,name'])
            ->orderBy('name')
            ->get();

        $filterDescription = collect([
            $memberTypeId ? 'Jenis: '.MemberType::query()->find($memberTypeId)?->name : null,
            $branchId ? 'Cabang: '.Branch::query()->find($branchId)?->name : null,
        ])->filter()->implode(' — ') ?: 'Semua Anggota';

        $pdf = Pdf::loadView('admin.anggota.daftar-pdf', [
            'members' => $members,
            'filterDescription' => $filterDescription,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('daftar-anggota-'.now()->format('Ymd-His').'.pdf');
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.anggota.create', [
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('name')->get(),
            'branches' => $this->availableBranches(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $data = collect($request->validated())->except('photo')->all();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store(self::PHOTO_DIRECTORY, self::PHOTO_DISK);
        }

        $member = Member::query()->create($data);

        return redirect()
            ->route('admin.members.index')
            ->with('status', "Anggota \"{$member->name}\" berhasil ditambahkan.");
    }

    public function edit(Member $member): View
    {
        $this->authorize('master_data.read');

        return view('admin.anggota.edit', [
            'member' => $member,
            'memberTypes' => MemberType::query()->where('is_active', true)->orderBy('name')->get(),
            'branches' => $this->availableBranches(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $data = collect($request->validated())->except('photo')->all();

        if ($request->hasFile('photo')) {
            if ($member->photo_path) {
                Storage::disk(self::PHOTO_DISK)->delete($member->photo_path);
            }

            $data['photo_path'] = $request->file('photo')->store(self::PHOTO_DIRECTORY, self::PHOTO_DISK);
        }

        $member->update($data);

        return redirect()
            ->route('admin.members.index')
            ->with('status', "Anggota \"{$member->name}\" berhasil diperbarui.");
    }

    private function availableBranches()
    {
        $allowed = request()->user()->allowedBranchIds();

        return $allowed === null
            ? Branch::query()->where('is_active', true)->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get();
    }
}

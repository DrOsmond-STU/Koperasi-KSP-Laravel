<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberTypeRequest;
use App\Http\Requests\UpdateMemberTypeRequest;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Scopes\BranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MemberTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        // withCount tidak dipakai di sini karena ikut ter-filter oleh
        // BranchScope milik user yang login — Jenis Anggota adalah master
        // data lintas cabang, jumlah pemakainya harus dihitung apa adanya
        // (lihat catatan yang sama di destroy()).
        $counts = Member::query()->withoutGlobalScope(BranchScope::class)
            ->selectRaw('member_type_id, count(*) as aggregate')
            ->groupBy('member_type_id')
            ->pluck('aggregate', 'member_type_id');

        return view('admin.master.jenis-anggota.index', [
            'types' => MemberType::query()->orderBy('name')->get(),
            'memberCounts' => $counts,
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.jenis-anggota.create');
    }

    public function store(StoreMemberTypeRequest $request): RedirectResponse
    {
        $type = MemberType::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.master.member-types.index')
            ->with('status', "Jenis anggota \"{$type->name}\" berhasil dibuat.");
    }

    public function edit(MemberType $memberType): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.jenis-anggota.edit', [
            'type' => $memberType,
        ]);
    }

    public function update(UpdateMemberTypeRequest $request, MemberType $memberType): RedirectResponse
    {
        $memberType->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.master.member-types.index')
            ->with('status', "Jenis anggota \"{$memberType->name}\" berhasil diperbarui.");
    }

    public function destroy(MemberType $memberType): RedirectResponse
    {
        $this->authorize('master_data.update');

        // withoutGlobalScope: guard ini harus melihat SEMUA cabang, bukan
        // hanya cabang yang terlihat oleh user yang login — kalau tidak,
        // user dengan Cabang Scope terbatas bisa lolos dari guard ini
        // (exists() salah balik false) padahal masih ada anggota di cabang
        // lain, dan DELETE akan gagal mentah di constraint FK.
        $usageCount = Member::query()->withoutGlobalScope(BranchScope::class)
            ->where('member_type_id', $memberType->id)
            ->count();

        if ($usageCount > 0) {
            return redirect()
                ->route('admin.master.member-types.index')
                ->with('error', "Jenis anggota \"{$memberType->name}\" tidak bisa dihapus — masih dipakai oleh {$usageCount} anggota. Nonaktifkan saja lewat menu Ubah.");
        }

        $name = $memberType->name;
        $memberType->delete();

        return redirect()
            ->route('admin.master.member-types.index')
            ->with('status', "Jenis anggota \"{$name}\" berhasil dihapus.");
    }
}

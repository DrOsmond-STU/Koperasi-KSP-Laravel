<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.cabang.index', [
            'branches' => Branch::query()->with('parentBranch')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.cabang.create', [
            'parentOptions' => Branch::query()->orderBy('code')->get(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $branch = Branch::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.master.branches.index')
            ->with('status', "Cabang \"{$branch->name}\" berhasil dibuat.");
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.cabang.edit', [
            'branch' => $branch,
            // Cabang tidak boleh menjadi induk dirinya sendiri maupun induk
            // dari salah satu keturunannya — pilihan yang tidak sah tidak
            // ditawarkan sama sekali, bukan hanya ditolak setelah submit.
            'parentOptions' => Branch::query()
                ->orderBy('code')
                ->get()
                ->reject(fn (Branch $candidate) => $this->isSelfOrDescendant($candidate, $branch)),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.master.branches.index')
            ->with('status', "Cabang \"{$branch->name}\" berhasil diperbarui.");
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('master_data.update');

        $name = $branch->name;

        // Ada 28 tabel yang menunjuk ke `branches` (anggota, jurnal, simpanan,
        // pinjaman, kas teller, audit log, dst.). Memeriksa semuanya satu per
        // satu di sini pasti akan ketinggalan saat tabel baru ditambah, jadi
        // penjaga sebenarnya adalah constraint FK di database — kita cukup
        // menerjemahkan kegagalannya menjadi pesan yang bisa ditindaklanjuti.
        try {
            $branch->delete();
        } catch (QueryException) {
            return redirect()
                ->route('admin.master.branches.index')
                ->with('error', "Cabang \"{$name}\" tidak bisa dihapus — masih ada data (anggota, transaksi, atau jurnal) yang tercatat di cabang ini. Nonaktifkan saja lewat menu Ubah.");
        }

        return redirect()
            ->route('admin.master.branches.index')
            ->with('status', "Cabang \"{$name}\" berhasil dihapus.");
    }

    private function isSelfOrDescendant(Branch $candidate, Branch $branch): bool
    {
        if ($candidate->is($branch)) {
            return true;
        }

        // Menelusuri ke atas dari kandidat: kalau $branch ditemukan sebagai
        // salah satu leluhurnya, berarti kandidat adalah keturunan $branch.
        $ancestor = $candidate->parentBranch;
        $guard = 0;

        while ($ancestor !== null && $guard++ < 50) {
            if ($ancestor->is($branch)) {
                return true;
            }

            $ancestor = $ancestor->parentBranch;
        }

        return false;
    }
}

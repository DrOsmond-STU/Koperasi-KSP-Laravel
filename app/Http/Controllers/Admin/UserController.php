<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Manajemen User & Role (PRD/SECURITY §user.manage, role.manage — permission
 * sudah ada di seeder & sudah digrant ke admin_sistem, belum ada UI-nya sama
 * sekali sampai sekarang). Tidak ada hard-delete akun (lihat migrasi
 * add_is_active_to_users_table) — hanya nonaktifkan/aktifkan.
 *
 * Form ini meng-assign role yang permission-nya SUDAH didefinisikan tetap di
 * RolePermissionSeeder — bukan editor matriks permission bebas per-role
 * (itu perubahan model wewenang yang lebih besar, di luar cakupan ini).
 */
class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('user.manage');

        return view('admin.users.index', [
            'users' => User::query()->with(['roles', 'branchScope.singleBranch', 'branchScope.branches'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('user.manage');

        return view('admin.users.create', [
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'unlinkedMembers' => Member::query()->whereNull('user_id')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => Hash::make($request->validated('password')),
                'is_active' => true,
                'mfa_enforced' => $request->boolean('enable_mfa'),
            ]);

            $user->syncRoles($request->validated('roles'));

            UserBranchScope::query()->create([
                'user_id' => $user->id,
                'scope_type' => $request->validated('scope_type'),
                'single_branch_id' => $request->validated('scope_type') === 'single' ? $request->validated('single_branch_id') : null,
            ]);

            if ($request->validated('scope_type') === 'multiple') {
                $user->branchScope->branches()->sync($request->validated('branch_ids'));
            }

            if ($request->filled('member_id')) {
                Member::query()->findOrFail($request->validated('member_id'))->update(['user_id' => $user->id]);
            }

            return $user;
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Pengguna \"{$user->name}\" berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        $this->authorize('user.manage');

        return view('admin.users.edit', [
            'targetUser' => $user->load(['roles', 'branchScope.branches', 'member']),
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'unlinkedMembers' => Member::query()->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $oldRoles = $user->getRoleNames()->all();

        DB::transaction(function () use ($request, $user) {
            $data = [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'mfa_enforced' => $request->boolean('enable_mfa'),
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->validated('password'));
            }

            $user->update($data);
            $user->syncRoles($request->validated('roles'));

            $scope = $user->branchScope()->firstOrNew();
            $scope->fill([
                'user_id' => $user->id,
                'scope_type' => $request->validated('scope_type'),
                'single_branch_id' => $request->validated('scope_type') === 'single' ? $request->validated('single_branch_id') : null,
            ])->save();

            $scope->branches()->sync($request->validated('scope_type') === 'multiple' ? $request->validated('branch_ids') : []);

            $newMemberId = $request->filled('member_id') ? (int) $request->validated('member_id') : null;
            $currentMember = $user->member;

            if ($currentMember?->id !== $newMemberId) {
                if ($currentMember !== null) {
                    $currentMember->update(['user_id' => null]);
                }
                if ($newMemberId !== null) {
                    Member::query()->findOrFail($newMemberId)->update(['user_id' => $user->id]);
                }
            }
        });

        $newRoles = $request->validated('roles');
        if ($oldRoles !== $newRoles) {
            $this->logRoleChange($user, $oldRoles, $newRoles);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Pengguna \"{$user->name}\" berhasil diperbarui.");
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('user.manage');

        abort_if($user->id === request()->user()->id, 403, 'Anda tidak bisa menonaktifkan akun Anda sendiri.');

        $user->update(['is_active' => false]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Pengguna \"{$user->name}\" telah dinonaktifkan.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        $this->authorize('user.manage');

        $user->update(['is_active' => true]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Pengguna \"{$user->name}\" telah diaktifkan kembali.");
    }

    /**
     * Hapus setup MFA milik user (secret, recovery codes, timestamp konfirmasi)
     * supaya mereka bisa mengulang aktivasi dari awal — dipakai bila user
     * kehilangan aplikasi authenticator / ganti ponsel. Setelah reset, user
     * yang wajib MFA otomatis diarahkan ke /mfa/setup pada login berikutnya
     * oleh EnsureMfaIsEnabledForInternalRoles.
     */
    public function resetMfa(User $user): RedirectResponse
    {
        $this->authorize('user.manage');

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', "MFA untuk \"{$user->name}\" berhasil direset. User akan diminta setup ulang saat login berikutnya.");
    }

    /**
     * syncRoles() menulis ke pivot model_has_roles, bukan kolom users —
     * tidak lolos otomatis lewat hook updated() milik Auditable, jadi
     * dicatat manual di sini (pola sama seperti AuditableObserver::record()).
     */
    private function logRoleChange(User $user, array $oldRoles, array $newRoles): void
    {
        $actor = request()->user();

        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->getRoleNames()->implode(','),
            'branch_id' => null,
            'action' => 'role_change',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'before' => ['roles' => $oldRoles],
            'after' => ['roles' => $newRoles],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Editor matriks Role & Permission (PRD/SECURITY §role.manage). Berbeda
 * dari role-assignment di Admin\UserController (yang meng-assign role KE
 * user) — controller ini mengubah IZIN APA yang dimiliki suatu role,
 * supaya Admin/Superadmin bisa menyesuaikan kebijakan internal koperasi
 * tanpa mengubah kode. RolePermissionSeeder hanya menyeed default sekali
 * saat role pertama dibuat — perubahan di sini permanen (tidak akan
 * ditimpa ulang oleh seeder).
 */
class RolePermissionController extends Controller
{
    /**
     * user.manage dan role.manage pada admin_sistem tidak boleh dicabut
     * lewat UI ini — mencegah Admin mengunci semua orang (termasuk dirinya
     * sendiri) dari pengelolaan user/role. Dipertahankan di server,
     * bukan hanya di UI.
     */
    private const PROTECTED_ROLE_PERMISSIONS = [
        'admin_sistem' => ['user.manage', 'role.manage'],
    ];

    public function index(): View
    {
        $this->authorize('role.manage');

        return view('admin.role-permission.index', [
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('role.manage');

        $permissionsByModule = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0]);

        return view('admin.role-permission.edit', [
            'role' => $role,
            'permissionsByModule' => $permissionsByModule,
            'assignedPermissions' => $role->permissions->pluck('name')->all(),
            'protectedPermissions' => self::PROTECTED_ROLE_PERMISSIONS[$role->name] ?? [],
        ]);
    }

    public function update(UpdateRolePermissionRequest $request, Role $role): RedirectResponse
    {
        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

        $newPermissions = $request->validated('permissions', []);
        $newPermissions = array_unique([
            ...$newPermissions,
            ...(self::PROTECTED_ROLE_PERMISSIONS[$role->name] ?? []),
        ]);

        $role->syncPermissions($newPermissions);

        $freshPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        if ($oldPermissions !== $freshPermissions) {
            $this->logPermissionChange($role, $oldPermissions, $freshPermissions);
        }

        return redirect()
            ->route('admin.role-permission.index')
            ->with('status', "Izin untuk role \"{$role->name}\" berhasil diperbarui.");
    }

    private function logPermissionChange(Role $role, array $oldPermissions, array $newPermissions): void
    {
        $actor = request()->user();

        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->getRoleNames()->implode(','),
            'branch_id' => null,
            'action' => 'permission_change',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'before' => ['permissions' => $oldPermissions],
            'after' => ['permissions' => $newPermissions],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

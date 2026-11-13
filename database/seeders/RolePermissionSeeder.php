<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the CRUD/Approve permission model from 02_SECURITY.md §Authorization.
 *
 * Permission naming convention: "{modul}.{aksi}" where aksi is one of
 * create/read/update/delete/approve (or a module-specific verb like
 * "lock" for saldo_awal, "calculate" for shu, "adjust" for jurnal).
 * "approve" (and equivalents) is always a distinct permission from
 * create/update — never bundled — to keep segregation of duties
 * enforceable at the Policy layer (see AUTH-09 in 06_TESTING.md).
 *
 * Cabang Scope (single/multiple/all) is NOT part of Spatie permissions —
 * it lives in `user_branch_scope` and is enforced by BranchScope /
 * EnsureBranchScope (ARCHITECTURE §6). A role only says WHAT a user can
 * do; scope says WHERE.
 *
 * ROLE_PERMISSIONS below is only the DEFAULT assignment applied the first
 * time a role is created. After that, Admin/Superadmin owns each role's
 * permission set via Admin\RolePermissionController — this seeder never
 * re-syncs an existing role's permissions on subsequent runs.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const MODULE_ACTIONS = [
        'simpanan' => ['create', 'read', 'update', 'delete', 'approve', 'print'],
        'pinjaman' => ['create', 'read', 'update', 'delete', 'approve', 'print'],
        // Beda dari 'pinjaman.delete' (membatalkan PENCAIRAN pinjaman —
        // manajer saja, lihat LoanApprovalService::cancelDisbursement()):
        // 'angsuran.delete' membatalkan SATU pembayaran angsuran yang salah
        // catat — jauh lebih sering terjadi & lebih ringan, jadi diberikan
        // ke role yang juga mencatat angsuran (Teller, Petugas Kredit),
        // sama pola dengan 'simpanan.delete'/'retribusi_upf.delete' di
        // bawah (laporan staf 26 Agu 2026).
        'angsuran' => ['delete'],
        'retribusi_upf' => ['create', 'read', 'delete'],
        'kas_teller' => ['create', 'read', 'update'],
        'pos' => ['create', 'read'],
        'pembelian' => ['create', 'read', 'approve'],
        'hutang_supplier_pembayaran' => ['create', 'read'],
        'persediaan_koreksi' => ['create', 'read', 'approve', 'delete'],
        'aktiva_tetap' => ['create', 'read', 'update', 'approve', 'delete'],
        'saldo_awal' => ['create', 'read', 'update', 'lock'],
        'master_data' => ['create', 'read', 'update'],
        'coa_mapping' => ['create', 'read', 'update'],
        'chart_of_account' => ['create', 'read', 'update', 'delete'],
        'jurnal' => ['read', 'adjust', 'create'],
        'laporan_keuangan' => ['read'],
        'laporan_kustom' => ['create', 'read'],
        'laporan' => ['read'],
        'tarif' => ['read', 'update'],
        'shu' => ['read', 'calculate'],
        'rat' => ['read', 'compose'],
        'kalender' => ['create', 'read', 'update', 'delete'],
        'notifikasi_template' => ['manage'],
        'notifikasi_log' => ['read'],
        'keamanan_audit' => ['read', 'export'],
        'user' => ['manage'],
        'role' => ['manage'],
        'branch_scope' => ['manage'],
        'security_config' => ['manage'],
        'unit_usaha' => ['create', 'read', 'update'],
        'branding' => ['manage'],
        'member_card' => ['manage', 'print'],
        'cetakan' => ['manage'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'anggota' => [
            'simpanan.read',
            'pinjaman.create',
            'pinjaman.read',
        ],
        'teller' => [
            'simpanan.create', 'simpanan.read', 'simpanan.update', 'simpanan.delete', 'simpanan.approve', 'simpanan.print',
            'kas_teller.create', 'kas_teller.read', 'kas_teller.update',
            'pos.create', 'pos.read',
            'pinjaman.create', 'pinjaman.read', 'pinjaman.print', 'angsuran.delete',
            'jurnal.read',
            'member_card.print',
        ],
        'petugas_kredit' => [
            'pinjaman.create', 'pinjaman.read', 'pinjaman.update', 'angsuran.delete',
        ],
        'petugas_upf' => [
            'retribusi_upf.create', 'retribusi_upf.read', 'retribusi_upf.delete',
        ],
        'manajer' => [
            // Semua permission Teller + Petugas Kredit + Petugas UPF, ditambah:
            'simpanan.create', 'simpanan.read', 'simpanan.update', 'simpanan.delete', 'simpanan.approve', 'simpanan.print',
            'kas_teller.create', 'kas_teller.read', 'kas_teller.update',
            'pos.create', 'pos.read',
            'pinjaman.create', 'pinjaman.read', 'pinjaman.update', 'pinjaman.approve', 'pinjaman.delete', 'pinjaman.print', 'angsuran.delete',
            'retribusi_upf.create', 'retribusi_upf.read', 'retribusi_upf.delete',
            'jurnal.read',
            'master_data.create', 'master_data.read', 'master_data.update',
            'unit_usaha.create', 'unit_usaha.read', 'unit_usaha.update',
            'tarif.read', 'tarif.update',
            'pembelian.create', 'pembelian.read', 'pembelian.approve',
            'persediaan_koreksi.create', 'persediaan_koreksi.read', 'persediaan_koreksi.approve', 'persediaan_koreksi.delete',
            'aktiva_tetap.create', 'aktiva_tetap.read', 'aktiva_tetap.update', 'aktiva_tetap.approve', 'aktiva_tetap.delete',
            'laporan_keuangan.read', 'laporan_kustom.create', 'laporan_kustom.read', 'laporan.read',
            'shu.read', 'shu.calculate', 'rat.read', 'rat.compose',
            'kalender.create', 'kalender.read', 'kalender.update', 'kalender.delete',
            'keamanan_audit.read',
            'member_card.manage', 'member_card.print',
        ],
        'bendahara' => [
            'coa_mapping.create', 'coa_mapping.read', 'coa_mapping.update',
            'saldo_awal.create', 'saldo_awal.read', 'saldo_awal.update', 'saldo_awal.lock',
            'jurnal.read', 'jurnal.adjust', 'jurnal.create',
            'laporan_keuangan.read', 'laporan_kustom.create', 'laporan_kustom.read', 'laporan.read',
            'hutang_supplier_pembayaran.create', 'hutang_supplier_pembayaran.read',
            'persediaan_koreksi.read', 'persediaan_koreksi.approve',
            'aktiva_tetap.read', 'aktiva_tetap.approve',
            'shu.read', 'shu.calculate',
            'retribusi_upf.read', 'master_data.read',
            'keamanan_audit.read',
        ],
        'pengawas' => [
            'simpanan.read', 'simpanan.print', 'pinjaman.read', 'pinjaman.print', 'kas_teller.read', 'pos.read',
            'retribusi_upf.read', 'jurnal.read',
            'laporan_keuangan.read', 'laporan_kustom.read', 'laporan.read',
            'tarif.read', 'shu.read', 'rat.read', 'kalender.read',
            'aktiva_tetap.read', 'persediaan_koreksi.read', 'pembelian.read',
            'notifikasi_log.read',
            'keamanan_audit.read', 'keamanan_audit.export',
        ],
        'admin_sistem' => [
            'user.manage', 'role.manage', 'branch_scope.manage', 'security_config.manage',
            'notifikasi_template.manage', 'notifikasi_log.read',
            'keamanan_audit.read', 'branding.manage', 'member_card.manage',
            'simpanan.print', 'pinjaman.print', 'cetakan.manage',
            'chart_of_account.create', 'chart_of_account.read', 'chart_of_account.update', 'chart_of_account.delete',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODULE_ACTIONS as $module => $actions) {
            foreach ($actions as $action) {
                Permission::query()->firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            // Only seed the default permission set the first time a role is
            // created. Once it exists, its permissions become owned by
            // Admin/Superadmin via the Role & Permission UI — re-running
            // this seeder (migrate:fresh --seed, deploys) must never revert
            // their customizations back to these hardcoded defaults.
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(array_unique($permissions));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Seeded %d permissions across %d roles.',
            Permission::query()->count(),
            Role::query()->count(),
        ));
    }
}

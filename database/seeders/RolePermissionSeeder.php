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
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const MODULE_ACTIONS = [
        'simpanan' => ['create', 'read', 'update', 'delete'],
        'pinjaman' => ['create', 'read', 'update', 'delete', 'approve'],
        'upf_tagihan' => ['create', 'read'],
        'upf_pembayaran' => ['create', 'read'],
        'kas_teller' => ['create', 'read', 'update'],
        'pos' => ['create', 'read'],
        'pembelian' => ['create', 'read', 'approve'],
        'hutang_supplier_pembayaran' => ['create', 'read'],
        'persediaan_koreksi' => ['create', 'read', 'approve'],
        'aktiva_tetap' => ['create', 'read', 'update', 'approve'],
        'saldo_awal' => ['create', 'read', 'update', 'lock'],
        'master_data' => ['create', 'read', 'update'],
        'coa_mapping' => ['create', 'read', 'update'],
        'jurnal' => ['read', 'adjust'],
        'laporan_keuangan' => ['read'],
        'laporan_kustom' => ['create', 'read'],
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
            'simpanan.create', 'simpanan.read', 'simpanan.update',
            'kas_teller.create', 'kas_teller.read', 'kas_teller.update',
            'pos.create', 'pos.read',
            'pinjaman.create', 'pinjaman.read',
            'jurnal.read',
            'member_card.print',
        ],
        'petugas_kredit' => [
            'pinjaman.create', 'pinjaman.read', 'pinjaman.update',
        ],
        'petugas_upf' => [
            'upf_tagihan.create', 'upf_tagihan.read',
            'upf_pembayaran.create', 'upf_pembayaran.read',
        ],
        'manajer' => [
            // Semua permission Teller + Petugas Kredit + Petugas UPF, ditambah:
            'simpanan.create', 'simpanan.read', 'simpanan.update',
            'kas_teller.create', 'kas_teller.read', 'kas_teller.update',
            'pos.create', 'pos.read',
            'pinjaman.create', 'pinjaman.read', 'pinjaman.update', 'pinjaman.approve',
            'upf_tagihan.create', 'upf_tagihan.read',
            'upf_pembayaran.create', 'upf_pembayaran.read',
            'jurnal.read',
            'master_data.create', 'master_data.read', 'master_data.update',
            'unit_usaha.create', 'unit_usaha.read', 'unit_usaha.update',
            'tarif.read', 'tarif.update',
            'pembelian.create', 'pembelian.read', 'pembelian.approve',
            'persediaan_koreksi.create', 'persediaan_koreksi.read', 'persediaan_koreksi.approve',
            'aktiva_tetap.create', 'aktiva_tetap.read', 'aktiva_tetap.update', 'aktiva_tetap.approve',
            'laporan_keuangan.read', 'laporan_kustom.create', 'laporan_kustom.read',
            'shu.read', 'shu.calculate', 'rat.read', 'rat.compose',
            'kalender.create', 'kalender.read', 'kalender.update', 'kalender.delete',
            'keamanan_audit.read',
            'member_card.manage', 'member_card.print',
        ],
        'bendahara' => [
            'coa_mapping.create', 'coa_mapping.read', 'coa_mapping.update',
            'saldo_awal.create', 'saldo_awal.read', 'saldo_awal.update', 'saldo_awal.lock',
            'jurnal.read', 'jurnal.adjust',
            'laporan_keuangan.read', 'laporan_kustom.create', 'laporan_kustom.read',
            'hutang_supplier_pembayaran.create', 'hutang_supplier_pembayaran.read',
            'persediaan_koreksi.read', 'persediaan_koreksi.approve',
            'aktiva_tetap.read', 'aktiva_tetap.approve',
            'shu.read', 'shu.calculate',
            'keamanan_audit.read',
        ],
        'pengawas' => [
            'simpanan.read', 'pinjaman.read', 'kas_teller.read', 'pos.read',
            'upf_tagihan.read', 'upf_pembayaran.read', 'jurnal.read',
            'laporan_keuangan.read', 'laporan_kustom.read',
            'tarif.read', 'shu.read', 'rat.read', 'kalender.read',
            'aktiva_tetap.read', 'persediaan_koreksi.read', 'pembelian.read',
            'notifikasi_log.read',
            'keamanan_audit.read', 'keamanan_audit.export',
        ],
        'admin_sistem' => [
            'user.manage', 'role.manage', 'branch_scope.manage', 'security_config.manage',
            'notifikasi_template.manage', 'notifikasi_log.read',
            'keamanan_audit.read', 'branding.manage',
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
            $role->syncPermissions(array_unique($permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Seeded %d permissions across %d roles.',
            Permission::query()->count(),
            Role::query()->count(),
        ));
    }
}

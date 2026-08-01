<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Services\Accounting\JournalEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bagan Akun admin CRUD — deliberately scoped to admin_sistem only (not the
 * shared master_data.* permission other master-data screens use), per
 * explicit user request: only admin can add/edit/delete accounts.
 */
class ChartOfAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminSistem(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_manajer_cannot_view_or_manage_chart_of_accounts(): void
    {
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create();

        $this->actingAs($user)->get(route('admin.master.chart-of-accounts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.chart-of-accounts.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.chart-of-accounts.edit', $account))->assertForbidden();
    }

    public function test_admin_sistem_can_view_the_list(): void
    {
        $admin = $this->adminSistem();
        ChartOfAccount::factory()->create(['code' => '9001']);

        $response = $this->actingAs($admin)->get(route('admin.master.chart-of-accounts.index'));

        $response->assertOk();
        $response->assertSee('9001');
    }

    public function test_admin_sistem_can_create_an_account(): void
    {
        $admin = $this->adminSistem();
        $parent = ChartOfAccount::factory()->create(['code' => '9000', 'is_postable' => false]);

        $response = $this->actingAs($admin)->post(route('admin.master.chart-of-accounts.store'), [
            'code' => '9010',
            'name' => 'Akun Uji',
            'type' => 'ASET',
            'group' => 'Aset Lancar',
            'normal_balance' => 'DEBIT',
            'is_postable' => '1',
            'parent_code' => $parent->code,
            'statement' => 'NERACA',
            'notes' => 'Dibuat untuk pengujian',
        ]);

        $response->assertRedirect(route('admin.master.chart-of-accounts.index'));
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => '9010',
            'name' => 'Akun Uji',
            'is_postable' => true,
            'parent_code' => '9000',
        ]);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $admin = $this->adminSistem();
        ChartOfAccount::factory()->create(['code' => '9020']);

        $response = $this->actingAs($admin)->post(route('admin.master.chart-of-accounts.store'), [
            'code' => '9020',
            'name' => 'Duplikat',
            'type' => 'ASET',
            'normal_balance' => 'DEBIT',
            'statement' => 'NERACA',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_admin_sistem_can_update_an_account(): void
    {
        $admin = $this->adminSistem();
        $account = ChartOfAccount::factory()->create(['code' => '9030', 'name' => 'Nama Lama']);

        $response = $this->actingAs($admin)->put(route('admin.master.chart-of-accounts.update', $account), [
            'code' => '9030',
            'name' => 'Nama Baru',
            'type' => $account->type,
            'normal_balance' => $account->normal_balance,
            'statement' => $account->statement,
        ]);

        $response->assertRedirect(route('admin.master.chart-of-accounts.index'));
        $this->assertEquals('Nama Baru', $account->fresh()->name);
    }

    public function test_renaming_a_code_cascades_to_children(): void
    {
        $admin = $this->adminSistem();
        $parent = ChartOfAccount::factory()->create(['code' => '9040']);
        $child = ChartOfAccount::factory()->create(['code' => '9041', 'parent_code' => '9040']);

        $this->actingAs($admin)->put(route('admin.master.chart-of-accounts.update', $parent), [
            'code' => '9045',
            'name' => $parent->name,
            'type' => $parent->type,
            'normal_balance' => $parent->normal_balance,
            'statement' => $parent->statement,
        ]);

        $this->assertEquals('9045', $child->fresh()->parent_code);
    }

    public function test_an_account_cannot_be_its_own_parent(): void
    {
        $admin = $this->adminSistem();
        $account = ChartOfAccount::factory()->create(['code' => '9050']);

        $response = $this->actingAs($admin)->put(route('admin.master.chart-of-accounts.update', $account), [
            'code' => '9050',
            'name' => $account->name,
            'type' => $account->type,
            'normal_balance' => $account->normal_balance,
            'parent_code' => '9050',
            'statement' => $account->statement,
        ]);

        $response->assertSessionHasErrors('parent_code');
    }

    public function test_a_cyclical_parent_chain_is_rejected(): void
    {
        $admin = $this->adminSistem();
        $grandparent = ChartOfAccount::factory()->create(['code' => '9060']);
        $parent = ChartOfAccount::factory()->create(['code' => '9061', 'parent_code' => '9060']);

        // Trying to make the grandparent a child of its own descendant.
        $response = $this->actingAs($admin)->put(route('admin.master.chart-of-accounts.update', $grandparent), [
            'code' => '9060',
            'name' => $grandparent->name,
            'type' => $grandparent->type,
            'normal_balance' => $grandparent->normal_balance,
            'parent_code' => $parent->code,
            'statement' => $grandparent->statement,
        ]);

        $response->assertSessionHasErrors('parent_code');
    }

    public function test_protected_account_code_cannot_be_changed(): void
    {
        $admin = $this->adminSistem();
        $kas = ChartOfAccount::factory()->create(['code' => '1101', 'name' => 'Kas']);

        $response = $this->actingAs($admin)->put(route('admin.master.chart-of-accounts.update', $kas), [
            'code' => '1199',
            'name' => 'Kas',
            'type' => $kas->type,
            'normal_balance' => $kas->normal_balance,
            'statement' => $kas->statement,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertEquals('1101', $kas->fresh()->code);
    }

    public function test_protected_account_cannot_be_deleted(): void
    {
        $admin = $this->adminSistem();
        $kas = ChartOfAccount::factory()->create(['code' => '1101']);

        $response = $this->actingAs($admin)->delete(route('admin.master.chart-of-accounts.destroy', $kas));

        $response->assertRedirect();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $kas->id]);
    }

    public function test_account_with_children_cannot_be_deleted(): void
    {
        $admin = $this->adminSistem();
        $parent = ChartOfAccount::factory()->create(['code' => '9070']);
        ChartOfAccount::factory()->create(['code' => '9071', 'parent_code' => '9070']);

        $response = $this->actingAs($admin)->delete(route('admin.master.chart-of-accounts.destroy', $parent));

        $response->assertRedirect();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $parent->id]);
    }

    public function test_account_referenced_by_a_journal_line_cannot_be_deleted(): void
    {
        $admin = $this->adminSistem();
        $account = ChartOfAccount::factory()->create(['code' => '9080']);
        $otherAccount = ChartOfAccount::factory()->create(['code' => '9081']);
        $branch = Branch::factory()->create();

        app(JournalEngine::class)->post([
            'branch_id' => $branch->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Uji referensi akun',
            'created_by' => $admin->id,
            'lines' => [
                ['chart_of_account_id' => $account->id, 'debit' => 1000, 'credit' => 0],
                ['chart_of_account_id' => $otherAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.master.chart-of-accounts.destroy', $account));

        $response->assertRedirect();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $account->id]);
    }

    public function test_an_unused_account_can_be_deleted(): void
    {
        $admin = $this->adminSistem();
        $account = ChartOfAccount::factory()->create(['code' => '9090']);

        $response = $this->actingAs($admin)->delete(route('admin.master.chart-of-accounts.destroy', $account));

        $response->assertRedirect(route('admin.master.chart-of-accounts.index'));
        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $account->id]);
    }
}

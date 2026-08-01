<?php

namespace Tests\Feature\Admin;

use App\Models\ChartOfAccount;
use App\Models\RetributionType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetributionTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_index_and_create_are_gated_not_just_store(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('admin.master.retribution-types.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.retribution-types.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.master.retribution-types.store'), [])->assertForbidden();
    }

    public function test_creating_type_defaults_to_inactive_even_without_coa(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.retribution-types.store'), [
            'code' => 'KEB',
            'name' => 'Retribusi Kebersihan',
            'percentage' => 20,
        ]);

        $response->assertRedirect(route('admin.master.retribution-types.index'));
        $this->assertDatabaseHas('retribution_types', [
            'code' => 'KEB',
            'coa_revenue_account_id' => null,
            'is_active' => 0,
        ]);
    }

    public function test_activating_type_without_coa_link_is_rejected(): void
    {
        $user = $this->manajer();
        $type = RetributionType::factory()->create(['coa_revenue_account_id' => null, 'is_active' => false]);

        $response = $this->actingAs($user)->put(route('admin.master.retribution-types.update', $type), [
            'code' => $type->code,
            'name' => $type->name,
            'percentage' => $type->percentage,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['is_active']);
        $this->assertDatabaseHas('retribution_types', ['id' => $type->id, 'is_active' => 0]);
    }

    public function test_activating_type_with_coa_link_succeeds(): void
    {
        $user = $this->manajer();
        $account = ChartOfAccount::factory()->create(['is_postable' => true]);
        $type = RetributionType::factory()->create(['coa_revenue_account_id' => null, 'is_active' => false]);

        $response = $this->actingAs($user)->put(route('admin.master.retribution-types.update', $type), [
            'code' => $type->code,
            'name' => $type->name,
            'percentage' => $type->percentage,
            'coa_revenue_account_id' => $account->id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.retribution-types.index'));
        $this->assertDatabaseHas('retribution_types', ['id' => $type->id, 'is_active' => 1, 'coa_revenue_account_id' => $account->id]);
    }
}

<?php

namespace Tests\Feature\Prints;

use App\Models\Member;
use App\Models\MemberCardField;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\MemberCardTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perbaikan Cetakan Kartu Anggota (Fase 18) — logo koperasi bisa dipasang
 * di desainer kartu, dan cetak massal (bukan cuma satu-satu) sekarang
 * tersedia.
 */
class MemberCardBulkPrintTest extends TestCase
{
    use RefreshDatabase;

    private function memberCardManager(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    public function test_logo_is_a_valid_field_key(): void
    {
        $this->assertContains('logo', MemberCardField::FIELD_KEYS);
    }

    public function test_bulk_print_generates_one_pdf_for_multiple_members(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);
        $user = $this->memberCardManager();
        Member::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('admin.members.card.print-bulk'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_bulk_print_can_be_scoped_to_selected_members(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);
        $user = $this->memberCardManager();
        $selected = Member::factory()->create();
        Member::factory()->count(2)->create();

        $response = $this->actingAs($user)->get(route('admin.members.card.print-bulk', ['member_id' => [$selected->id]]));

        $response->assertOk();
    }

    public function test_bulk_print_without_any_matching_member_returns_404(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);
        $user = $this->memberCardManager();

        $this->actingAs($user)->get(route('admin.members.card.print-bulk', ['member_id' => [999999]]))->assertNotFound();
    }

    public function test_role_without_member_card_print_cannot_bulk_print(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('bendahara');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->get(route('admin.members.card.print-bulk'))->assertForbidden();
    }
}

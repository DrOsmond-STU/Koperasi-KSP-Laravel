<?php

namespace Tests\Feature\MemberCard;

use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\MemberCardTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(MemberCardTemplateSeeder::class);
    }

    /**
     * Gives the user an "All Branch" Cabang Scope so BranchScope doesn't
     * hide the Member row before the permission check is ever reached
     * (PRD §6 — Cabang Scope is a separate dimension from role permission).
     */
    private function giveAllBranchScope(User $user): void
    {
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $member = Member::factory()->create();

        $this->get(route('admin.members.card', $member))
            ->assertRedirect(route('login'));
    }

    public function test_role_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('anggota');
        $this->giveAllBranchScope($user);

        $member = Member::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.members.card', $member))
            ->assertForbidden();
    }

    public function test_teller_can_view_and_download_card(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('teller');
        $this->giveAllBranchScope($user);

        $member = Member::factory()->create(['name' => 'Dewi Lestari']);

        $this->actingAs($user)
            ->get(route('admin.members.card', $member))
            ->assertOk()
            ->assertSee('Dewi Lestari');

        $response = $this->actingAs($user)->get(route('admin.members.card.pdf', $member));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}

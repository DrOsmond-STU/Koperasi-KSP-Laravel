<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserBranchScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kelengkapan Audit Log — login/logout tidak lolos lewat hook Auditable
 * model manapun (bukan perubahan kolom), jadi diuji lewat alur HTTP asli
 * (bukan actingAs(), yang melewati event Login Laravel sungguhan).
 */
class AuditLogCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audit_logged(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SomeRandomPass9')]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'SomeRandomPass9',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_logout_is_audit_logged(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        $this->actingAs($user)->post(route('logout'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'logout',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_journal_entry_creation_is_audit_logged(): void
    {
        $this->assertAuditableModelIsTracked(\App\Models\JournalEntry::class);
    }

    public function test_savings_transaction_creation_is_audit_logged(): void
    {
        $this->assertAuditableModelIsTracked(\App\Models\SavingsTransaction::class);
    }

    private function assertAuditableModelIsTracked(string $modelClass): void
    {
        $this->assertContains(
            \App\Models\Concerns\Auditable::class,
            class_uses_recursive($modelClass),
            "{$modelClass} diharapkan pakai trait Auditable."
        );
    }

    public function test_audit_log_page_can_filter_by_actor_and_date_range(): void
    {
        $this->seedRolePermissions();
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        AuditLog::query()->create([
            'actor_id' => $user->id,
            'actor_role' => 'admin_sistem',
            'action' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.keamanan-audit.log', [
            'actor_id' => $user->id,
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    private function seedRolePermissions(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }
}

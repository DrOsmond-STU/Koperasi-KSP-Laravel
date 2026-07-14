<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md §11 (AUD-01, AUD-02, AUD-04).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /** AUD-01: creating an Auditable model records actor/branch/action/after. */
    public function test_creating_a_branch_is_recorded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::query()->create([
            'code' => 'CBG-02',
            'name' => 'Cabang Kedua',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'create',
            'auditable_type' => Branch::class,
            'auditable_id' => $branch->id,
        ]);
    }

    /** AUD-02: updating a model records before/after values. */
    public function test_updating_a_branch_records_before_and_after(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::query()->create([
            'code' => 'CBG-03',
            'name' => 'Nama Lama',
            'is_active' => true,
        ]);

        $branch->update(['name' => 'Nama Baru']);

        $log = AuditLog::query()
            ->where('auditable_type', Branch::class)
            ->where('auditable_id', $branch->id)
            ->where('action', 'update')
            ->firstOrFail();

        $this->assertEquals('Nama Lama', $log->before['name']);
        $this->assertEquals('Nama Baru', $log->after['name']);
    }

    /** AUD-04: audit_logs rows can never be updated or deleted, even by direct model call. */
    public function test_audit_log_rows_are_immutable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Branch::query()->create(['code' => 'CBG-04', 'name' => 'Cabang', 'is_active' => true]);

        $log = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_rows_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Branch::query()->create(['code' => 'CBG-05', 'name' => 'Cabang', 'is_active' => true]);

        $log = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $log->delete();
    }
}

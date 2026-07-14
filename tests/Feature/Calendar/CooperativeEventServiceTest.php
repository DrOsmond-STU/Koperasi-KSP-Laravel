<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Services\Calendar\CooperativeEventService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CooperativeEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_event_with_multiple_branches_syncs_pivot(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create();

        $event = app(CooperativeEventService::class)->create([
            'title' => 'Pelatihan Petugas',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'branch_scope_type' => 'multiple',
            'branch_ids' => [$branchA->id, $branchB->id],
            'participant_type' => 'semua_anggota_cabang',
            'reminder_days' => [1, 0],
        ], $user->id);

        $this->assertEquals('terjadwal', $event->status);
        $this->assertEqualsCanonicalizing([$branchA->id, $branchB->id], $event->branchIds());
    }

    public function test_creating_event_with_specific_members_syncs_pivot(): void
    {
        $member1 = Member::factory()->create();
        $member2 = Member::factory()->create();
        $user = User::factory()->create();

        $event = app(CooperativeEventService::class)->create([
            'title' => 'Undangan Khusus',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(1),
            'branch_scope_type' => 'all',
            'participant_type' => 'anggota_tertentu',
            'member_ids' => [$member1->id, $member2->id],
        ], $user->id);

        $this->assertEqualsCanonicalizing([$member1->id, $member2->id], $event->members->pluck('id')->all());
    }

    public function test_creating_event_with_specific_role_syncs_pivot(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::query()->where('name', 'petugas_kredit')->firstOrFail();
        $user = User::factory()->create();

        $event = app(CooperativeEventService::class)->create([
            'title' => 'Rapat Internal',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(1),
            'branch_scope_type' => 'all',
            'participant_type' => 'role_tertentu',
            'role_ids' => [$role->id],
        ], $user->id);

        $this->assertEqualsCanonicalizing([$role->id], $event->roles->pluck('id')->all());
    }

    public function test_cancel_transitions_status_to_dibatalkan(): void
    {
        $user = User::factory()->create();
        $event = app(CooperativeEventService::class)->create([
            'title' => 'Kegiatan Dibatalkan',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(1),
            'branch_scope_type' => 'all',
            'participant_type' => 'semua_anggota_cabang',
        ], $user->id);

        $cancelled = app(CooperativeEventService::class)->cancel($event);

        $this->assertEquals('dibatalkan', $cancelled->status);
    }
}

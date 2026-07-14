<?php

namespace App\Services\Calendar;

use App\Models\CooperativeEvent;
use Illuminate\Support\Facades\DB;

/**
 * CRUD Kegiatan Koperasi (PRD §15.1, Task 4.1) — the manual source feeding
 * CalendarService alongside automatic loan due dates.
 */
class CooperativeEventService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $createdBy): CooperativeEvent
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $event = CooperativeEvent::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'location' => $data['location'] ?? null,
                'branch_scope_type' => $data['branch_scope_type'],
                'single_branch_id' => $data['branch_scope_type'] === 'single' ? $data['single_branch_id'] : null,
                'participant_type' => $data['participant_type'],
                'reminder_days' => $data['reminder_days'] ?? [],
                'status' => 'terjadwal',
                'created_by' => $createdBy,
            ]);

            $this->syncRelations($event, $data);

            return $event->fresh(['branches', 'members', 'roles']);
        });
    }

    /**
     * "Kegiatan yang dibatalkan mengirim notifikasi pembatalan ke seluruh
     * peserta terdaftar" (PRD §15.1) — the actual dispatch is wired in once
     * Task 4.4 (NotificationService) exists; this only performs the status
     * transition for now.
     */
    public function cancel(CooperativeEvent $event): CooperativeEvent
    {
        $event->update(['status' => 'dibatalkan']);

        return $event->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(CooperativeEvent $event, array $data): void
    {
        if ($data['branch_scope_type'] === 'multiple') {
            $event->branches()->sync($data['branch_ids'] ?? []);
        }

        if ($data['participant_type'] === 'anggota_tertentu') {
            $event->members()->sync($data['member_ids'] ?? []);
        } elseif ($data['participant_type'] === 'role_tertentu') {
            $event->roles()->sync($data['role_ids'] ?? []);
        }
    }
}

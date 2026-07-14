<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\Notification\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generic queued notification dispatch (PRD §16, Task 4.4) — trigger-
 * specific jobs (e.g. SendDueDateNotification) resolve WHO/WHAT to notify,
 * then hand off to this job so the actual send always goes through the
 * queue (never inline in a request).
 */
class SendNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(
        public readonly string $triggerType,
        public readonly int $memberId,
        public readonly array $variables,
        public readonly string $dedupeKey,
    ) {}

    public function handle(NotificationDispatchService $dispatcher): void
    {
        $member = Member::query()->find($this->memberId);

        if ($member === null) {
            return;
        }

        $dispatcher->send($this->triggerType, $member, $this->variables, $this->dedupeKey);
    }
}

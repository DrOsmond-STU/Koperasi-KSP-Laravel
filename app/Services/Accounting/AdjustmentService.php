<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\AdjustmentException;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Jurnal Penyesuaian (PRD §2.7, Task 5.2). Every correction goes through
 * JournalEngine::reverse() — the original entry is NEVER edited/deleted
 * (LED-06) — and requires the acting user to re-confirm their password
 * immediately before posting (re-auth, PRD §5.2 "konfirmasi re-auth/OTP"),
 * on top of whatever permission gate already applies to the route.
 */
class AdjustmentService
{
    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function reverse(JournalEntry $originalEntry, string $reason, User $user, string $password): JournalEntry
    {
        if (! Hash::check($password, $user->password)) {
            throw AdjustmentException::reAuthFailed();
        }

        return $this->journalEngine->reverse($originalEntry, $reason, $user->id);
    }
}

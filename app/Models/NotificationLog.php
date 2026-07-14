<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only business record of every notification attempt (PRD §16).
 * Not to be confused with application/error logs — PII-02's redaction
 * requirement is about the LATTER, not this table, which must keep the
 * actual sent content for audit/compliance/resend.
 */
class NotificationLog extends Model
{
    protected $fillable = [
        'dedupe_key',
        'trigger_type',
        'channel',
        'notifiable_type',
        'notifiable_id',
        'recipient',
        'subject',
        'message',
        'status',
        'cost',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}

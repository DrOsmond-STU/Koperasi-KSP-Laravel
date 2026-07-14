<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;

/**
 * Renders notification templates (PRD §16, Task 4.2). Placeholders are
 * substituted with `strtr()` — a fixed, safe find-and-replace — never
 * `eval()` or a templating engine that could execute arbitrary code from
 * admin-editable content.
 */
class NotificationTemplateResolver
{
    /**
     * @param  array<string, string>  $variables  e.g. ['nama_anggota' => 'Budi', 'nominal_angsuran' => 'Rp 450.000']
     * @return array{subject: ?string, body: string}|null null if no active template exists for this trigger/channel
     */
    public function resolve(string $triggerType, string $channel, array $variables): ?array
    {
        $template = NotificationTemplate::query()
            ->where('trigger_type', $triggerType)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return null;
        }

        $replacements = collect($variables)->mapWithKeys(fn ($value, $key) => ["{{$key}}" => $value])->all();

        return [
            'subject' => $template->subject !== null ? strtr($template->subject, $replacements) : null,
            'body' => strtr($template->body, $replacements),
        ];
    }
}

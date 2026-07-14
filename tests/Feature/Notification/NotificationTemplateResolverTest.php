<?php

namespace Tests\Feature\Notification;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Notification\NotificationTemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md NOTIF-07: template diubah Admin -> notifikasi
 * berikutnya memakai versi baru; log yang sudah tercatat tidak berubah
 * retroaktif.
 */
class NotificationTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_substitutes_placeholders_via_strtr(): void
    {
        NotificationTemplate::factory()->create([
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'body' => 'Yth. {nama_anggota}, angsuran {no_pinjaman} sebesar {nominal_angsuran}.',
        ]);

        $rendered = app(NotificationTemplateResolver::class)->resolve('jatuh_tempo_angsuran', 'whatsapp', [
            'nama_anggota' => 'Budi Santoso',
            'no_pinjaman' => 'PINJ-001',
            'nominal_angsuran' => 'Rp 450.000',
        ]);

        $this->assertEquals('Yth. Budi Santoso, angsuran PINJ-001 sebesar Rp 450.000.', $rendered['body']);
    }

    public function test_resolver_returns_null_for_inactive_or_missing_template(): void
    {
        $rendered = app(NotificationTemplateResolver::class)->resolve('jatuh_tempo_angsuran', 'whatsapp', []);

        $this->assertNull($rendered);
    }

    public function test_updating_template_changes_future_resolution_without_altering_past_logs(): void
    {
        $template = NotificationTemplate::factory()->create([
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'body' => 'Versi lama: {nama_anggota}.',
        ]);

        $resolver = app(NotificationTemplateResolver::class);
        $oldRendered = $resolver->resolve('jatuh_tempo_angsuran', 'whatsapp', ['nama_anggota' => 'Budi']);

        $log = NotificationLog::query()->create([
            'dedupe_key' => 'test-key-1',
            'trigger_type' => 'jatuh_tempo_angsuran',
            'channel' => 'whatsapp',
            'recipient' => '628123456789',
            'message' => $oldRendered['body'],
            'status' => 'terkirim',
            'sent_at' => now(),
        ]);

        $template->update(['body' => 'Versi baru: {nama_anggota}.']);

        $newRendered = $resolver->resolve('jatuh_tempo_angsuran', 'whatsapp', ['nama_anggota' => 'Budi']);

        $this->assertEquals('Versi baru: Budi.', $newRendered['body']);
        $this->assertEquals('Versi lama: Budi.', $log->fresh()->message);
    }
}

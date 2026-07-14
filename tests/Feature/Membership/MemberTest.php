<?php

namespace Tests\Feature\Membership;

use App\Models\Member;
use App\Models\MemberContactConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers 06_TESTING.md §10 (PII-01, PII-06) and the consent model backing
 * NOTIF-04/05.
 */
class MemberTest extends TestCase
{
    use RefreshDatabase;

    /** PII-01: NIK and other sensitive columns are not stored as plaintext. */
    public function test_sensitive_columns_are_encrypted_at_rest(): void
    {
        $member = Member::factory()->create(['nik' => '3201012345670001']);

        $raw = DB::table('members')->where('id', $member->id)->value('nik');

        $this->assertNotEquals('3201012345670001', $raw);
        $this->assertEquals('3201012345670001', $member->fresh()->nik);
    }

    /** PII-06: anonymize() clears identity/contact but keeps the row (and its id) intact. */
    public function test_anonymize_clears_identity_but_keeps_record(): void
    {
        $member = Member::factory()->create(['member_number' => 'AGT-0099']);
        $memberId = $member->id;

        $member->anonymize();

        $member->refresh();
        $this->assertEquals($memberId, $member->id);
        $this->assertEquals('Anggota #AGT-0099', $member->name);
        $this->assertNull($member->nik);
        $this->assertNull($member->phone);
        $this->assertEquals('keluar', $member->status);
        $this->assertDatabaseHas('members', ['id' => $memberId]);
    }

    /** Consent: withdrawing WhatsApp consent does not affect Email consent. */
    public function test_consent_is_tracked_independently_per_channel(): void
    {
        $member = Member::factory()->create();

        $whatsapp = MemberContactConsent::query()->create([
            'member_id' => $member->id,
            'channel' => 'whatsapp',
        ]);
        $email = MemberContactConsent::query()->create([
            'member_id' => $member->id,
            'channel' => 'email',
        ]);

        $whatsapp->grant();
        $email->grant();

        $this->assertTrue($member->hasConsent('whatsapp'));
        $this->assertTrue($member->hasConsent('email'));

        $whatsapp->withdraw();

        $this->assertFalse($member->hasConsent('whatsapp'));
        $this->assertTrue($member->hasConsent('email'));
    }
}

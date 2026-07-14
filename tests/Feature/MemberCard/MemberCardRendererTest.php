<?php

namespace Tests\Feature\MemberCard;

use App\Exceptions\MemberCard\NoActiveCardTemplateException;
use App\Models\Member;
use App\Models\MemberCardTemplate;
use App\Services\MemberCard\MemberCardRenderer;
use Database\Seeders\MemberCardTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCardRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_includes_member_identity_fields(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);

        $member = Member::factory()->create([
            'name' => 'Siti Aminah',
            'member_number' => 'AGT-1234',
        ]);

        $html = (string) app(MemberCardRenderer::class)->renderHtml($member);

        $this->assertStringContainsString('Siti Aminah', $html);
        $this->assertStringContainsString('AGT-1234', $html);
    }

    public function test_render_shows_initial_placeholder_when_no_photo(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);

        $member = Member::factory()->create(['name' => 'Budi Santoso', 'photo_path' => null]);

        $html = (string) app(MemberCardRenderer::class)->renderHtml($member);

        $this->assertStringContainsString('>B<', $html);
    }

    public function test_throws_when_no_active_template_exists(): void
    {
        // no seeding — no template rows at all
        $member = Member::factory()->create();

        $this->expectException(NoActiveCardTemplateException::class);

        app(MemberCardRenderer::class)->renderHtml($member);
    }

    public function test_only_one_template_can_be_active_at_a_time(): void
    {
        $this->seed(MemberCardTemplateSeeder::class);

        $second = MemberCardTemplate::query()->create([
            'name' => 'Template Kedua',
            'width_mm' => 85.6,
            'height_mm' => 53.98,
            'is_active' => false,
        ]);

        $second->activate();

        $this->assertEquals(1, MemberCardTemplate::query()->where('is_active', true)->count());
        $this->assertTrue($second->fresh()->is_active);
    }
}

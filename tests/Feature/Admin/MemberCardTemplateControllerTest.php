<?php

namespace Tests\Feature\Admin;

use App\Models\MemberCardTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCardTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function manajer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');

        return $user;
    }

    public function test_index_and_create_are_gated_not_just_store(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('anggota');

        $this->actingAs($user)->get(route('admin.master.member-card-templates.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.member-card-templates.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.master.member-card-templates.store'), [])->assertForbidden();
    }

    public function test_admin_sistem_also_has_manage_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('admin_sistem');

        $this->actingAs($user)->get(route('admin.master.member-card-templates.index'))->assertOk();
    }

    public function test_creating_template_with_fields_succeeds(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.member-card-templates.store'), [
            'name' => 'Template Uji',
            'width_mm' => 85.60,
            'height_mm' => 53.98,
            'background_color' => '#FFFFFF',
            'fields' => [
                ['field_key' => 'name', 'position_x_mm' => 4, 'position_y_mm' => 4, 'width_mm' => 40, 'height_mm' => 10],
                ['field_key' => 'member_number', 'position_x_mm' => 4, 'position_y_mm' => 16, 'width_mm' => 40, 'height_mm' => 8],
            ],
        ]);

        $response->assertRedirect(route('admin.master.member-card-templates.index'));
        $this->assertDatabaseHas('member_card_templates', ['name' => 'Template Uji']);
        $template = MemberCardTemplate::query()->where('name', 'Template Uji')->firstOrFail();
        $this->assertCount(2, $template->fields);
    }

    public function test_field_position_exceeding_card_bounds_is_rejected(): void
    {
        $user = $this->manajer();

        $response = $this->actingAs($user)->post(route('admin.master.member-card-templates.store'), [
            'name' => 'Template Salah',
            'width_mm' => 85.60,
            'height_mm' => 53.98,
            'fields' => [
                ['field_key' => 'name', 'position_x_mm' => 80, 'position_y_mm' => 4, 'width_mm' => 40, 'height_mm' => 10],
            ],
        ]);

        $response->assertSessionHasErrors(['fields.0.position_x_mm']);
        $this->assertDatabaseMissing('member_card_templates', ['name' => 'Template Salah']);
    }

    public function test_update_syncs_fields_add_edit_and_remove(): void
    {
        $user = $this->manajer();
        $template = MemberCardTemplate::factory()->create();
        $keepField = $template->fields()->create(['field_key' => 'name', 'position_x_mm' => 4, 'position_y_mm' => 4]);
        $removeField = $template->fields()->create(['field_key' => 'address', 'position_x_mm' => 4, 'position_y_mm' => 20]);

        $response = $this->actingAs($user)->put(route('admin.master.member-card-templates.update', $template), [
            'name' => $template->name,
            'width_mm' => $template->width_mm,
            'height_mm' => $template->height_mm,
            'fields' => [
                ['field_id' => $keepField->id, 'field_key' => 'name', 'position_x_mm' => 5, 'position_y_mm' => 5],
                ['field_key' => 'member_number', 'position_x_mm' => 4, 'position_y_mm' => 30],
            ],
        ]);

        $response->assertRedirect(route('admin.master.member-card-templates.index'));
        $this->assertDatabaseMissing('member_card_fields', ['id' => $removeField->id]);
        $this->assertDatabaseHas('member_card_fields', ['id' => $keepField->id, 'position_x_mm' => 5]);
        $this->assertDatabaseHas('member_card_fields', ['member_card_template_id' => $template->id, 'field_key' => 'member_number']);
        $this->assertCount(2, $template->fields()->get());
    }

    public function test_activate_deactivates_other_templates(): void
    {
        $user = $this->manajer();
        $activeTemplate = MemberCardTemplate::factory()->create(['is_active' => true]);
        $newTemplate = MemberCardTemplate::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->post(route('admin.master.member-card-templates.activate', $newTemplate));

        $response->assertRedirect(route('admin.master.member-card-templates.index'));
        $this->assertTrue($newTemplate->fresh()->is_active);
        $this->assertFalse($activeTemplate->fresh()->is_active);
    }
}

<?php

namespace Database\Seeders;

use App\Models\MemberCardTemplate;
use Illuminate\Database\Seeder;

/**
 * Default CR80 (85.6mm x 53.98mm) layout so a koperasi has a working card
 * out of the box — fully editable afterwards (position/size per field).
 */
class MemberCardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (MemberCardTemplate::query()->exists()) {
            return;
        }

        $template = MemberCardTemplate::query()->create([
            'name' => 'Kartu Anggota Standar (CR80)',
            'width_mm' => 85.60,
            'height_mm' => 53.98,
            'background_color' => '#FFFFFF',
            'is_active' => true,
        ]);

        $template->fields()->createMany([
            [
                'field_key' => 'custom_text',
                'custom_text_value' => 'KARTU ANGGOTA KOPERASI',
                'position_x_mm' => 4,
                'position_y_mm' => 2,
                'width_mm' => 78,
                'font_size_pt' => 7,
                'font_weight' => 'bold',
                'text_align' => 'center',
                'color' => '#11543B',
                'sort_order' => 1,
            ],
            [
                'field_key' => 'photo',
                'position_x_mm' => 4,
                'position_y_mm' => 8,
                'width_mm' => 20,
                'height_mm' => 25,
                'sort_order' => 2,
            ],
            [
                'field_key' => 'name',
                'position_x_mm' => 28,
                'position_y_mm' => 10,
                'width_mm' => 54,
                'font_size_pt' => 11,
                'font_weight' => 'bold',
                'text_align' => 'left',
                'sort_order' => 3,
            ],
            [
                'field_key' => 'member_number',
                'position_x_mm' => 28,
                'position_y_mm' => 18,
                'width_mm' => 54,
                'font_size_pt' => 9,
                'text_align' => 'left',
                'color' => '#5C6E64',
                'sort_order' => 4,
            ],
            [
                'field_key' => 'member_type_name',
                'position_x_mm' => 28,
                'position_y_mm' => 24,
                'width_mm' => 54,
                'font_size_pt' => 8,
                'color' => '#5C6E64',
                'sort_order' => 5,
            ],
            [
                'field_key' => 'branch_name',
                'position_x_mm' => 4,
                'position_y_mm' => 46,
                'width_mm' => 78,
                'font_size_pt' => 7,
                'text_align' => 'center',
                'color' => '#5C6E64',
                'sort_order' => 6,
            ],
        ]);

        $this->command?->info('Seeded default member card template with 6 fields.');
    }
}

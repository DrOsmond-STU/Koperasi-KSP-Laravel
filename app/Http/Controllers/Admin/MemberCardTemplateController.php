<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberCardTemplateRequest;
use App\Http\Requests\UpdateMemberCardTemplateRequest;
use App\Models\Member;
use App\Models\MemberCardField;
use App\Models\MemberCardTemplate;
use App\Services\Settings\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MemberCardTemplateController extends Controller
{
    private const IMAGE_DISK = 'public';

    private const IMAGE_DIRECTORY = 'member-card-backgrounds';

    public function index(): View
    {
        $this->authorize('member_card.manage');

        return view('admin.master.kartu-anggota.index', [
            'templates' => MemberCardTemplate::query()->withCount('fields')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('member_card.manage');

        return view('admin.master.kartu-anggota.create', [
            'fieldKeys' => MemberCardField::FIELD_KEYS,
            'sampleMember' => $this->sampleMemberPayload(),
        ]);
    }

    public function store(StoreMemberCardTemplateRequest $request): RedirectResponse
    {
        $template = DB::transaction(function () use ($request) {
            $template = MemberCardTemplate::query()->create([
                'name' => $request->validated('name'),
                'width_mm' => $request->validated('width_mm'),
                'height_mm' => $request->validated('height_mm'),
                'background_color' => $request->validated('background_color') ?: '#FFFFFF',
                'background_image_path' => $request->hasFile('background_image')
                    ? $request->file('background_image')->store(self::IMAGE_DIRECTORY, self::IMAGE_DISK)
                    : null,
                'is_active' => false,
            ]);

            $template->fields()->createMany($this->fieldRowsFromRequest($request->validated('fields')));

            return $template;
        });

        return redirect()
            ->route('admin.master.member-card-templates.index')
            ->with('status', "Template \"{$template->name}\" berhasil dibuat. Jadikan aktif kalau sudah sesuai.");
    }

    public function edit(MemberCardTemplate $memberCardTemplate): View
    {
        $this->authorize('member_card.manage');

        return view('admin.master.kartu-anggota.edit', [
            'template' => $memberCardTemplate->load('fields'),
            'fieldKeys' => MemberCardField::FIELD_KEYS,
            'sampleMember' => $this->sampleMemberPayload(),
        ]);
    }

    public function update(UpdateMemberCardTemplateRequest $request, MemberCardTemplate $memberCardTemplate): RedirectResponse
    {
        DB::transaction(function () use ($request, $memberCardTemplate) {
            $memberCardTemplate->update([
                'name' => $request->validated('name'),
                'width_mm' => $request->validated('width_mm'),
                'height_mm' => $request->validated('height_mm'),
                'background_color' => $request->validated('background_color') ?: '#FFFFFF',
                'background_image_path' => $request->hasFile('background_image')
                    ? $request->file('background_image')->store(self::IMAGE_DIRECTORY, self::IMAGE_DISK)
                    : $memberCardTemplate->background_image_path,
            ]);

            $this->syncFields($memberCardTemplate, $request->validated('fields'));
        });

        return redirect()
            ->route('admin.master.member-card-templates.index')
            ->with('status', "Template \"{$memberCardTemplate->name}\" berhasil diperbarui.");
    }

    public function activate(MemberCardTemplate $memberCardTemplate): RedirectResponse
    {
        $this->authorize('member_card.manage');

        $memberCardTemplate->activate();

        return redirect()
            ->route('admin.master.member-card-templates.index')
            ->with('status', "Template \"{$memberCardTemplate->name}\" sekarang aktif.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function fieldRowsFromRequest(array $fields): array
    {
        return collect($fields)->values()->map(fn (array $field, int $index) => [
            'field_key' => $field['field_key'],
            'custom_text_value' => $field['field_key'] === 'custom_text' ? ($field['custom_text_value'] ?? null) : null,
            'position_x_mm' => $field['position_x_mm'],
            'position_y_mm' => $field['position_y_mm'],
            'width_mm' => $field['width_mm'] ?? null,
            'height_mm' => $field['height_mm'] ?? null,
            'font_size_pt' => $field['font_size_pt'] ?? null,
            'font_weight' => $field['font_weight'] ?? null,
            'text_align' => $field['text_align'] ?? null,
            'color' => $field['color'] ?? '#16201C',
            'sort_order' => $field['sort_order'] ?? $index,
        ])->all();
    }

    /**
     * Sinkron field template dalam satu form terpadu: baris tanpa `field_id`
     * dibuat baru, baris dengan `field_id` diupdate, baris lama yang sudah
     * tidak ada di payload dihapus.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function syncFields(MemberCardTemplate $template, array $fields): void
    {
        $submittedIds = collect($fields)->pluck('field_id')->filter()->map(fn ($id) => (int) $id);

        $template->fields()->whereNotIn('id', $submittedIds)->delete();

        foreach ($this->fieldRowsFromRequest($fields) as $index => $row) {
            $fieldId = $fields[$index]['field_id'] ?? null;

            if ($fieldId) {
                $template->fields()->where('id', $fieldId)->update($row);
            } else {
                $template->fields()->create($row);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleMemberPayload(): array
    {
        $member = Member::query()->with(['branch', 'memberType'])->latest()->first();
        $logoUrl = app(BrandingService::class)->logoUrl();

        if ($member === null) {
            return [
                'name' => 'Nama Anggota Contoh',
                'member_number' => 'AGT-0000',
                'branch_name' => 'Kantor Pusat',
                'member_type_name' => 'Anggota Biasa',
                'joined_at' => now()->translatedFormat('d M Y'),
                'address' => 'Alamat contoh',
                'photo_url' => null,
                'logo_url' => $logoUrl,
            ];
        }

        return [
            'name' => $member->name,
            'member_number' => $member->member_number,
            'branch_name' => $member->branch?->name ?? '',
            'member_type_name' => $member->memberType?->name ?? '',
            'joined_at' => $member->joined_at?->translatedFormat('d M Y') ?? '',
            'address' => $member->address ?? '',
            'photo_url' => $member->photo_path ? Storage::disk('public')->url($member->photo_path) : null,
            'logo_url' => $logoUrl,
        ];
    }
}

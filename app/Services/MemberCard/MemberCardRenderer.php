<?php

namespace App\Services\MemberCard;

use App\Exceptions\MemberCard\NoActiveCardTemplateException;
use App\Models\Member;
use App\Models\MemberCardTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Renders a Member Card as absolutely-positioned HTML from a
 * MemberCardTemplate + its MemberCardField rows (PRD: "kartu anggota +
 * foto, pengaturan dibuat fleksibel agar user bisa mengatur posisi dan
 * format kartu anggota"). Positions are in millimeters so the same layout
 * maps 1:1 between on-screen preview and printed PDF.
 */
class MemberCardRenderer
{
    public function resolveTemplate(?MemberCardTemplate $template = null): MemberCardTemplate
    {
        $template ??= MemberCardTemplate::active();

        if ($template === null) {
            throw new NoActiveCardTemplateException;
        }

        return $template->loadMissing('fields');
    }

    public function renderHtml(Member $member, ?MemberCardTemplate $template = null): HtmlString
    {
        $template = $this->resolveTemplate($template);

        $backgroundImage = $template->background_image_path
            ? "background-image: url('".Storage::disk('public')->url($template->background_image_path)."'); background-size: cover;"
            : '';

        $fieldsHtml = $template->fields->map(fn ($field) => $this->renderField($field, $member))->implode('');

        return new HtmlString(<<<HTML
            <div class="member-card" style="position: relative; width: {$template->width_mm}mm; height: {$template->height_mm}mm; background-color: {$template->background_color}; {$backgroundImage} overflow: hidden; border-radius: 2mm; font-family: sans-serif;">
                {$fieldsHtml}
            </div>
        HTML);
    }

    private function renderField($field, Member $member): string
    {
        $style = sprintf(
            'position: absolute; left: %smm; top: %smm;%s%s',
            $field->position_x_mm,
            $field->position_y_mm,
            $field->width_mm ? " width: {$field->width_mm}mm;" : '',
            $field->height_mm ? " height: {$field->height_mm}mm;" : '',
        );

        if ($field->isPhoto()) {
            $photoUrl = $member->photo_path
                ? Storage::disk('public')->url($member->photo_path)
                : null;

            if ($photoUrl) {
                return "<img src=\"{$photoUrl}\" style=\"{$style} object-fit: cover;\" alt=\"Foto {$member->name}\">";
            }

            $initial = mb_strtoupper(mb_substr($member->name, 0, 1));

            return "<div style=\"{$style} display: flex; align-items: center; justify-content: center; background: #E9F1EC; color: #11543B; font-weight: 700;\">{$initial}</div>";
        }

        $textStyle = $style
            .($field->font_size_pt ? " font-size: {$field->font_size_pt}pt;" : '')
            .($field->font_weight ? " font-weight: {$field->font_weight};" : '')
            .($field->text_align ? " text-align: {$field->text_align};" : '')
            ." color: {$field->color};";

        $value = e($field->resolveValue($member));

        return "<div style=\"{$textStyle}\">{$value}</div>";
    }
}

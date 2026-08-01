{{--
    Renders every signature line for a cetakan: any data-driven signers
    passed in via $extraSigners (e.g. the actual loan applicant, the
    journal entry's preparer) come first, followed by the admin-configured
    static slots for $documentGroup (App\Models\DocumentSignatureSlot) —
    see Admin\SignatureConfigController.

    Usage: @include('prints.partials.signature-block', [
        'documentGroup' => 'jurnal_umum',
        'extraSigners' => [['label' => 'Penginput', 'name' => $entry->createdBy->name]],
    ])
--}}
@php
    $slots = \App\Models\DocumentSignatureSlot::query()
        ->where('document_group', $documentGroup)
        ->with('signatory')
        ->orderBy('slot_order')
        ->get();

    $signers = collect($extraSigners ?? [])
        ->concat($slots->map(fn ($slot) => ['label' => $slot->label, 'name' => $slot->signatory?->name]));

    $columnWidth = $signers->count() > 0 ? (int) floor(100 / $signers->count()) : 100;
@endphp
@if ($signers->isNotEmpty())
    <table style="width:100%; margin-top:40px; border-collapse:collapse;">
        <tr>
            @foreach ($signers as $signer)
                <td style="text-align:center; padding:0 10px; width:{{ $columnWidth }}%;">
                    <p style="margin:0 0 50px;">{{ $signer['label'] }},</p>
                    <p style="margin:0; border-top:1px solid #16201C; padding-top:4px;">{!! $signer['name'] !== null ? e($signer['name']) : '&nbsp;' !!}</p>
                </td>
            @endforeach
        </tr>
    </table>
@endif

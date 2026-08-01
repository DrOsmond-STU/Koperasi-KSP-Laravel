<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignatureSlot extends Model
{
    use Auditable;

    protected $fillable = [
        'document_group',
        'slot_order',
        'label',
        'document_signatory_id',
    ];

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(DocumentSignatory::class, 'document_signatory_id');
    }
}

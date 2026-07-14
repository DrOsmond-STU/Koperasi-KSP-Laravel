<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShuAllocationCategory extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'percentage',
        'is_jasa_anggota',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_jasa_anggota' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

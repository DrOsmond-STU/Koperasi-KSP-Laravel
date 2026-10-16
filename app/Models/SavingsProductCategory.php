<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori Produk Simpanan — data master yang bisa dikelola koperasi.
 *
 * Dirujuk oleh savings_products.category lewat kolom `code` (bukan foreign
 * key numerik), supaya nilai enum lama tetap valid tanpa migrasi data.
 */
class SavingsProductCategory extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function savingsProducts(): HasMany
    {
        return $this->hasMany(SavingsProduct::class, 'category', 'code');
    }
}

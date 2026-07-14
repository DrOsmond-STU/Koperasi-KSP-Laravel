<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * PII columns (nik, address, phone, email, date_of_birth) are encrypted at
 * rest via the `encrypted` cast (SECURITY.md §PII handling). `name` is kept
 * plaintext — it is not on the encrypted-column list and is needed for
 * everyday display/search without a decrypt round-trip.
 */
class Member extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'member_type_id',
        'user_id',
        'member_number',
        'name',
        'nik',
        'address',
        'phone',
        'email',
        'date_of_birth',
        'photo_path',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'address' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'joined_at' => 'date',
        ];
    }

    public function memberType(): BelongsTo
    {
        return $this->belongsTo(MemberType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(MemberContactConsent::class);
    }

    public function savingsAccounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }

    public function hasConsent(string $channel): bool
    {
        return (bool) $this->consents()
            ->where('channel', $channel)
            ->where('consented', true)
            ->whereNull('withdrawn_at')
            ->exists();
    }

    /**
     * Retensi & anonymize (PRD §16.3, SECURITY.md, RUNBOOK §3.8 / PII-06):
     * identitas & kontak dianonimkan setelah kewajiban keuangan selesai —
     * id dan member_number tetap utuh agar jurnal/stock ledger/kartu
     * penyusutan historis yang mereferensikan anggota ini tetap valid.
     */
    public function anonymize(): void
    {
        if ($this->photo_path) {
            Storage::disk('public')->delete($this->photo_path);
        }

        $this->update([
            'name' => 'Anggota #'.$this->member_number,
            'nik' => null,
            'address' => null,
            'phone' => null,
            'email' => null,
            'date_of_birth' => null,
            'photo_path' => null,
            'status' => 'keluar',
        ]);
    }
}

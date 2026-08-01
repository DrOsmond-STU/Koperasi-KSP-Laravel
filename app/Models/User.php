<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_recovery_codes', 'two_factor_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branchScope(): HasOne
    {
        return $this->hasOne(UserBranchScope::class);
    }

    /**
     * Links this login to the koperasi member it belongs to — nullable, only
     * ever set for the `anggota` role (staf/admin accounts have no member
     * record). Powers the member self-service portal's ownership scoping.
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Branch IDs this user is allowed to access.
     * Returns null when scope is "all" — caller must treat null as unrestricted.
     *
     * Deliberately NOT written as `$this->branchScope?->allowedBranchIds() ?? []`
     * — the `??` operator can't tell "no scope row exists" apart from
     * "scope row exists and legitimately means null/unrestricted" (scope_type
     * = all), and would collapse both to `[]`, silently turning "All Branch"
     * into "zero branches".
     */
    public function allowedBranchIds(): ?array
    {
        $scope = $this->branchScope;

        if ($scope === null) {
            return [];
        }

        return $scope->allowedBranchIds();
    }

    /**
     * True for accounts that belong exclusively to Portal Anggota — the
     * `anggota` role and no other (staff/admin) role, with a linked Member
     * record to actually use the portal with. A staff account that also
     * happens to be a member still counts as staff. Drives both the
     * post-login redirect (RoleAwareLoginResponse) and which layout chrome
     * wraps shared pages like Profil Saya, so an anggota never gets dropped
     * into the admin sidebar.
     */
    public function isPortalOnlyMember(): bool
    {
        return $this->hasRole('anggota') && $this->roles->count() === 1 && $this->member !== null;
    }

    /**
     * SECURITY.md §Authentication: MFA wajib untuk seluruh role internal
     * (Teller, Petugas Kredit, Petugas UPF, Bendahara, Manajer/Pengurus,
     * Pengawas, Admin Sistem). Anggota: opsional.
     */
    public function requiresMfa(): bool
    {
        return $this->hasAnyRole([
            'teller',
            'petugas_kredit',
            'petugas_upf',
            'bendahara',
            'manajer',
            'pengawas',
            'admin_sistem',
        ]);
    }
}

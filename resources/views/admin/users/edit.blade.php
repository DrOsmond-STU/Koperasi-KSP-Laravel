@extends('layouts.app')

@section('title', 'Ubah Pengguna')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 620px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .role-list { display: flex; flex-direction: column; gap: 8px; border: 1px solid var(--line); border-radius: 9px; padding: 12px; max-height: 220px; overflow-y: auto; }
        .role-list label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--pine-ink); }
        .permission-preview { margin-top: 10px; padding: 10px 12px; background: var(--leaf); border-radius: 9px; font-size: 11.5px; color: var(--pine-bright); }
        .scope-options { display: flex; gap: 16px; margin-bottom: 10px; }
        .scope-options label { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; }
        .branch-checks { display: flex; flex-direction: column; gap: 6px; border: 1px solid var(--line); border-radius: 9px; padding: 12px; max-height: 160px; overflow-y: auto; }
        .branch-checks label { display: flex; align-items: center; gap: 8px; font-size: 13px; }
    </style>

    <h2>Ubah Pengguna — {{ $targetUser->name }}</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $currentRoles = old('roles', $targetUser->roles->pluck('name')->all());
        $currentScopeType = old('scope_type', $targetUser->branchScope?->scope_type ?? 'all');
        $currentBranchIds = old('branch_ids', $targetUser->branchScope?->branches->pluck('id')->all() ?? []);
        $currentEnableMfa = (bool) old('enable_mfa', $targetUser->mfa_enforced);
    @endphp

    @if (session('status'))
        <p style="color: var(--ok); font-size: 13px; margin-bottom: 14px;">{{ session('status') }}</p>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" id="user-form">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Nama</label>
                <input type="text" name="name" value="{{ old('name', $targetUser->name) }}" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $targetUser->email) }}" required>
            </div>
            <div class="field-row">
                <div class="field">
                    <label>Password Baru</label>
                    <input type="password" name="password">
                    <p class="hint">Kosongkan jika tidak ingin mengubah password.</p>
                </div>
                <div class="field">
                    <label>Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation">
                </div>
            </div>

            <div class="field">
                <label>Role</label>
                <div class="role-list" id="role-list">
                    @foreach ($roles as $role)
                        <label>
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                data-permissions="{{ $role->permissions->pluck('name')->implode(', ') }}"
                                @checked(in_array($role->name, $currentRoles))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                <div class="permission-preview" id="permission-preview">Pilih role untuk melihat daftar akses.</div>
            </div>

            <div class="field">
                <label>Autentikasi Dua Faktor (MFA)</label>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:500;">
                    <input type="checkbox" name="enable_mfa" value="1" id="enable-mfa" @checked($currentEnableMfa)>
                    <span>Wajibkan aktivasi MFA saat login</span>
                </label>
                <p class="hint" id="mfa-hint">
                    Untuk role internal (teller, petugas kredit, bendahara, manajer, pengawas, admin sistem) MFA wajib dan tidak bisa dinonaktifkan.
                </p>
                <p class="hint" style="margin-top:8px;">
                    Status setup:
                    <strong style="color: {{ $targetUser->two_factor_confirmed_at ? 'var(--ok)' : 'var(--brick)' }};">
                        {{ $targetUser->two_factor_confirmed_at ? 'Sudah dikonfirmasi user' : 'Belum dikonfirmasi user' }}
                    </strong>
                </p>
            </div>

            <div class="field" id="member-link-field" style="display:none;">
                <label>Data Anggota Terkait</label>
                <select name="member_id" class="js-searchable">
                    <option value="">— Tidak Ditautkan —</option>
                    @foreach ($unlinkedMembers as $member)
                        <option value="{{ $member->id }}" @selected(old('member_id', $targetUser->member?->id) == $member->id)>{{ $member->member_number }} — {{ $member->name }}</option>
                    @endforeach
                </select>
                <p class="hint">Menautkan akun login ini ke data anggota supaya bisa masuk ke Portal Anggota dan melihat data simpanan/pinjamannya sendiri.</p>
            </div>

            <div class="field">
                <label>Cabang</label>
                <div class="scope-options">
                    <label><input type="radio" name="scope_type" value="all" @checked($currentScopeType === 'all')> Semua Cabang</label>
                    <label><input type="radio" name="scope_type" value="single" @checked($currentScopeType === 'single')> Satu Cabang</label>
                    <label><input type="radio" name="scope_type" value="multiple" @checked($currentScopeType === 'multiple')> Beberapa Cabang</label>
                </div>
                <div id="scope-single" style="display:none;">
                    <select name="single_branch_id">
                        <option value="">— Pilih Cabang —</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('single_branch_id', $targetUser->branchScope?->single_branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="scope-multiple" style="display:none;">
                    <div class="branch-checks">
                        @foreach ($branches as $branch)
                            <label>
                                <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $currentBranchIds))>
                                {{ $branch->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>

    @if ($targetUser->two_factor_secret)
        <div class="form-card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Reset MFA</h3>
            <p class="hint" style="margin-bottom:12px;">
                Hapus setup 2FA milik user (secret, recovery codes, konfirmasi) — dipakai bila user
                kehilangan aplikasi authenticator. User akan diminta setup ulang di /mfa/setup
                pada login berikutnya.
            </p>
            <form method="POST" action="{{ route('admin.users.reset-mfa', $targetUser) }}"
                  onsubmit="return confirm('Reset setup MFA untuk {{ $targetUser->name }}? User akan diminta setup ulang pada login berikutnya.');">
                @csrf
                <button type="submit" class="btn-primary" style="background: var(--brick);">Reset MFA User</button>
            </form>
        </div>
    @endif

    <script>
        (function () {
            var roleChecks = document.querySelectorAll('#role-list input[type="checkbox"]');
            var preview = document.getElementById('permission-preview');

            function updatePreview() {
                var perms = [];
                roleChecks.forEach(function (cb) {
                    if (cb.checked && cb.dataset.permissions) {
                        perms = perms.concat(cb.dataset.permissions.split(', '));
                    }
                });
                perms = Array.from(new Set(perms)).filter(Boolean);
                preview.textContent = perms.length ? ('Akses: ' + perms.join(', ')) : 'Pilih role untuk melihat daftar akses.';
            }

            var memberField = document.getElementById('member-link-field');

            function updateMemberField() {
                var anggotaChecked = Array.from(roleChecks).some(function (cb) { return cb.value === 'anggota' && cb.checked; });
                memberField.style.display = anggotaChecked ? 'block' : 'none';
            }

            // Same pola dengan form Create — kunci checkbox MFA saat role internal terpilih,
            // tapi tanpa `disabled` supaya nilai tetap terkirim ke server.
            var mfaRequiredRoles = ['teller', 'petugas_kredit', 'petugas_upf', 'bendahara', 'manajer', 'pengawas', 'admin_sistem'];
            var enableMfa = document.getElementById('enable-mfa');
            var mfaHint = document.getElementById('mfa-hint');
            var mfaForced = false;

            function updateMfaLock() {
                mfaForced = Array.from(roleChecks).some(function (cb) { return cb.checked && mfaRequiredRoles.indexOf(cb.value) !== -1; });
                if (mfaForced) {
                    enableMfa.checked = true;
                    mfaHint.textContent = 'Role internal terpilih — MFA wajib aktif, tidak bisa dinonaktifkan.';
                } else {
                    mfaHint.textContent = 'Opsional untuk role anggota — centang jika Anda ingin memaksa aktivasi MFA untuk akun ini.';
                }
            }
            enableMfa.addEventListener('click', function (event) {
                if (mfaForced && ! enableMfa.checked) {
                    event.preventDefault();
                    enableMfa.checked = true;
                }
            });

            roleChecks.forEach(function (cb) { cb.addEventListener('change', updatePreview); cb.addEventListener('change', updateMemberField); cb.addEventListener('change', updateMfaLock); });
            updatePreview();
            updateMemberField();
            updateMfaLock();

            var scopeRadios = document.querySelectorAll('input[name="scope_type"]');
            var scopeSingle = document.getElementById('scope-single');
            var scopeMultiple = document.getElementById('scope-multiple');

            function updateScope() {
                var value = document.querySelector('input[name="scope_type"]:checked')?.value;
                scopeSingle.style.display = value === 'single' ? 'block' : 'none';
                scopeMultiple.style.display = value === 'multiple' ? 'block' : 'none';
            }

            scopeRadios.forEach(function (radio) { radio.addEventListener('change', updateScope); });
            updateScope();
        })();
    </script>
@endsection

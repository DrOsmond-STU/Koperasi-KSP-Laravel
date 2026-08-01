@extends('layouts.app')

@section('title', 'Tambah Pengguna')

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
        .role-list { display: flex; flex-direction: column; gap: 8px; border: 1px solid var(--line); border-radius: 9px; padding: 12px; max-height: 220px; overflow-y: auto; }
        .role-list label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--pine-ink); }
        .permission-preview { margin-top: 10px; padding: 10px 12px; background: var(--leaf); border-radius: 9px; font-size: 11.5px; color: var(--pine-bright); }
        .scope-options { display: flex; gap: 16px; margin-bottom: 10px; }
        .scope-options label { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; }
        .branch-checks { display: flex; flex-direction: column; gap: 6px; border: 1px solid var(--line); border-radius: 9px; padding: 12px; max-height: 160px; overflow-y: auto; }
        .branch-checks label { display: flex; align-items: center; gap: 8px; font-size: 13px; }
    </style>

    <h2>Tambah Pengguna</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.users.store') }}" id="user-form">
            @csrf

            <div class="field">
                <label>Nama</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>
            <div class="field-row">
                <div class="field">
                    <label>Password Awal</label>
                    <input type="password" name="password" required>
                </div>
                <div class="field">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="password_confirmation" required>
                </div>
            </div>

            <div class="field">
                <label>Role</label>
                <div class="role-list" id="role-list">
                    @foreach ($roles as $role)
                        <label>
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                data-permissions="{{ $role->permissions->pluck('name')->implode(', ') }}"
                                @checked(in_array($role->name, old('roles', [])))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                <div class="permission-preview" id="permission-preview">Pilih role untuk melihat daftar akses.</div>
            </div>

            <div class="field" id="member-link-field" style="display:none;">
                <label>Data Anggota Terkait</label>
                <select name="member_id" class="js-searchable">
                    <option value="">— Tidak Ditautkan —</option>
                    @foreach ($unlinkedMembers as $member)
                        <option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>{{ $member->member_number }} — {{ $member->name }}</option>
                    @endforeach
                </select>
                <p class="hint" style="font-size:11px; color:var(--muted); margin-top:4px;">Menautkan akun login ini ke data anggota supaya bisa masuk ke Portal Anggota dan melihat data simpanan/pinjamannya sendiri.</p>
            </div>

            <div class="field">
                <label>Cabang</label>
                <div class="scope-options">
                    <label><input type="radio" name="scope_type" value="all" @checked(old('scope_type', 'all') === 'all')> Semua Cabang</label>
                    <label><input type="radio" name="scope_type" value="single" @checked(old('scope_type') === 'single')> Satu Cabang</label>
                    <label><input type="radio" name="scope_type" value="multiple" @checked(old('scope_type') === 'multiple')> Beberapa Cabang</label>
                </div>
                <div id="scope-single" style="display:none;">
                    <select name="single_branch_id">
                        <option value="">— Pilih Cabang —</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('single_branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="scope-multiple" style="display:none;">
                    <div class="branch-checks">
                        @foreach ($branches as $branch)
                            <label>
                                <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, old('branch_ids', [])))>
                                {{ $branch->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary">Simpan Pengguna</button>
        </form>
    </div>

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

            roleChecks.forEach(function (cb) { cb.addEventListener('change', updatePreview); cb.addEventListener('change', updateMemberField); });
            updatePreview();
            updateMemberField();

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

@extends($user->isPortalOnlyMember() ? 'layouts.portal' : 'layouts.app')

@section('title', 'Profil Saya')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 520px; margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .mfa-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .mfa-status.on { background: var(--leaf); color: var(--pine-bright); }
        .mfa-status.off { background: var(--brick-soft); color: var(--brick); }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Profil Saya</h2>

    @php
        $statusMessages = [
            'profile-information-updated' => 'Profil berhasil diperbarui.',
            'password-updated' => 'Password berhasil diubah.',
        ];
    @endphp
    @if (session('status') && isset($statusMessages[session('status')]))
        <p class="status-msg">{{ $statusMessages[session('status')] }}</p>
    @endif

    <div class="form-card">
        <h3 style="margin-top:0;">Informasi Akun</h3>
        <form method="POST" action="{{ route('user-profile-information.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Nama</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name', 'updateProfileInformation')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email', 'updateProfileInformation')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>

    <div class="form-card">
        <h3 style="margin-top:0;">Ubah Password</h3>
        <form method="POST" action="{{ route('user-password.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label>Password Saat Ini</label>
                <input type="password" name="current_password" required>
                @error('current_password', 'updatePassword')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label>Password Baru</label>
                <input type="password" name="password" required>
                @error('password', 'updatePassword')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="password_confirmation" required>
            </div>

            <button type="submit" class="btn-primary">Ubah Password</button>
        </form>
    </div>

    <div class="form-card">
        <h3 style="margin-top:0;">Autentikasi Dua Faktor (2FA)</h3>
        <span class="mfa-status {{ $user->two_factor_confirmed_at ? 'on' : 'off' }}">
            {{ $user->two_factor_confirmed_at ? 'Aktif' : 'Nonaktif' }}
        </span>
        <p class="hint">Pengaktifan/penonaktifan 2FA dilakukan oleh Admin Sistem.</p>
    </div>
@endsection

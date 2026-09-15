@extends($user->isPortalOnlyMember() ? 'layouts.portal' : 'layouts.app')

@section('title', 'Aktivasi MFA')

@section('content')
    <style>
        .mfa-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 24px; max-width: 560px; margin-bottom: 20px; }
        .mfa-steps { list-style: decimal; padding-left: 20px; margin: 12px 0 18px; color: var(--pine-ink); font-size: 13.5px; line-height: 1.6; }
        .mfa-steps li + li { margin-top: 6px; }
        .qr-frame { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 12px; width: 220px; height: 220px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; }
        .qr-frame svg { max-width: 100%; max-height: 100%; }
        .secret-key { display: block; text-align: center; font-family: 'Menlo', 'Consolas', monospace; font-size: 13px; letter-spacing: 2px; padding: 8px 12px; background: var(--paper); border: 1px dashed var(--line); border-radius: 8px; margin-bottom: 16px; word-break: break-all; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 18px; text-align: center; letter-spacing: 8px; font-family: 'Menlo', 'Consolas', monospace; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-ghost { padding: 10px 18px; background: transparent; color: var(--muted); border: 1px solid var(--line); border-radius: 9px; font-weight: 600; cursor: pointer; margin-left: 8px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.5; }
        .recovery-note { background: var(--gold-soft); border-radius: 9px; padding: 12px; margin-top: 16px; font-size: 12px; color: var(--pine-ink); }
    </style>

    <h2>Aktivasi Autentikasi Dua Faktor (MFA)</h2>
    <p class="hint">Akun Anda mewajibkan MFA. Ikuti langkah berikut untuk mengaktifkannya sebelum melanjutkan.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="mfa-card">
        <ol class="mfa-steps">
            <li>Instal aplikasi authenticator (Google Authenticator, Microsoft Authenticator, Authy, atau serupa) di ponsel Anda.</li>
            <li>Buka aplikasi dan pindai QR code di bawah, atau masukkan kode setup secara manual.</li>
            <li>Masukkan 6 digit kode yang muncul di aplikasi ke kolom di bawahnya, lalu klik "Aktifkan".</li>
        </ol>

        <div class="qr-frame">
            {!! $qrCodeSvg !!}
        </div>

        <span class="secret-key" aria-label="Kode setup manual">{{ $secretKey }}</span>

        <form method="POST" action="{{ route('mfa.setup.confirm') }}">
            @csrf
            <div class="field">
                <label>Kode 6 digit dari aplikasi</label>
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required autofocus>
                @error('code', 'confirmTwoFactorAuthentication')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn-primary">Aktifkan</button>
        </form>

        <form method="POST" action="{{ route('mfa.setup.regenerate') }}" style="display:inline-block; margin-top:12px;">
            @csrf
            <button type="submit" class="btn-ghost">Ganti QR &amp; Mulai Ulang</button>
        </form>

        <div class="recovery-note">
            Setelah MFA aktif, kunjungi halaman <em>Profil Saya</em> untuk melihat dan menyimpan kode pemulihan (recovery codes) — kode itu diperlukan bila Anda kehilangan akses ke aplikasi authenticator.
        </div>
    </div>
@endsection

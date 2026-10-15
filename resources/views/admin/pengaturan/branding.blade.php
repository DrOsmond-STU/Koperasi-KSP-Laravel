@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')

@section('content')
    <style>
        .settings-card {
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 22px; max-width: 480px; margin-bottom: 18px;
        }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input[type=text], .field input[type=number], .field select {
            width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid var(--line); border-radius: 9px;
        }
        .field input[type=range] { width: 100%; }
        .field-hint { font-size: 11px; color: var(--muted); margin-top: 5px; }
        .current-logo { max-height: 48px; display: block; margin-bottom: 10px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .card-title { font-size: 14px; font-weight: 800; margin: 0 0 4px; }
        .card-sub { font-size: 12px; color: var(--muted); margin: 0 0 18px; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; }
        .checkbox-row label { margin: 0; font-size: 12px; }

        /* Kotak pratinjau memakai mekanisme yang sama persis dengan halaman
           login (kerudung gradient di atas gambar), supaya yang terlihat di
           sini benar-benar mewakili hasil akhirnya. */
        :root { --veil-rgb: 244,247,244; }
        :root[data-theme="b"] { --veil-rgb: 10,21,18; }
        .bg-preview {
            height: 170px; border: 1px solid var(--line); border-radius: 10px;
            background-color: var(--paper); background-position: center; margin-bottom: 10px;
        }
        .bg-preview-empty {
            display: flex; align-items: center; justify-content: center;
            color: var(--muted); font-size: 12px; text-align: center; padding: 0 16px;
        }
    </style>

    <h2>Tampilan Aplikasi</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data">
        @csrf

        <div class="settings-card">
            <p class="card-title">Nama &amp; Logo Aplikasi</p>
            <p class="card-sub">Tampil di halaman login, topbar, dan dokumen cetak.</p>

            <div class="field">
                <label for="app_name">Nama Aplikasi</label>
                <input type="text" id="app_name" name="app_name" value="{{ old('app_name', $setting->app_name) }}">
                @error('app_name') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Logo Saat Ini</label>
                @if($setting->logo_path)
                    <img class="current-logo" src="{{ \Illuminate\Support\Facades\Storage::url($setting->logo_path) }}" alt="Logo">
                @else
                    <p style="color: var(--muted); font-size: 13px;">Belum ada logo — memakai inisial nama.</p>
                @endif
            </div>

            <div class="field">
                <label for="logo">Ganti Logo (PNG/JPG/SVG, maks 2MB)</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml">
                @error('logo') <div class="error-text">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="settings-card">
            <p class="card-title">Gambar Latar Halaman Login</p>
            <p class="card-sub">Berlaku untuk halaman login dan halaman publik lain (lupa kata sandi, verifikasi 2FA).</p>

            <div class="field">
                <label>Pratinjau</label>
                @if($setting->login_background_path)
                    <div class="bg-preview" id="bgPreview"
                         data-url="{{ \Illuminate\Support\Facades\Storage::url($setting->login_background_path) }}"></div>
                @else
                    <div class="bg-preview bg-preview-empty" id="bgPreview" data-url="">
                        Belum ada gambar latar — halaman login memakai gradient bawaan tema.
                    </div>
                @endif
            </div>

            <div class="field">
                <label for="login_background">Unggah Gambar (PNG/JPG/WEBP, maks 4MB)</label>
                <input type="file" id="login_background" name="login_background" accept="image/png,image/jpeg,image/webp">
                <div class="field-hint">Disarankan gambar melebar minimal 1920&times;1080 piksel agar tidak pecah di layar besar.</div>
                @error('login_background') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="login_background_size">Penyesuaian Ukuran</label>
                <select id="login_background_size" name="login_background_size">
                    @foreach($sizeModes as $value => $label)
                        <option value="{{ $value }}" @selected(old('login_background_size', $setting->login_background_size ?? 'cover') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('login_background_size') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field" id="scaleField">
                <label for="login_background_scale">Skala Kustom (persen dari lebar layar)</label>
                <input type="number" id="login_background_scale" name="login_background_scale" min="10" max="400" step="5"
                       value="{{ old('login_background_scale', $setting->login_background_scale ?? 100) }}">
                <div class="field-hint">Hanya dipakai bila mode ukuran di atas diatur ke &ldquo;Skala kustom&rdquo;.</div>
                @error('login_background_scale') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="login_background_opacity">
                    Ketebalan Gambar: <span id="opacityValue">{{ old('login_background_opacity', $setting->login_background_opacity ?? 100) }}</span>%
                </label>
                <input type="range" id="login_background_opacity" name="login_background_opacity" min="0" max="100" step="1"
                       value="{{ old('login_background_opacity', $setting->login_background_opacity ?? 100) }}">
                <div class="field-hint">100% = gambar tampil penuh, 0% = gambar tidak terlihat. Turunkan nilainya bila teks pada kartu login sulit dibaca.</div>
                @error('login_background_opacity') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            @if($setting->login_background_path)
                <div class="field checkbox-row">
                    <input type="checkbox" id="remove_login_background" name="remove_login_background" value="1">
                    <label for="remove_login_background">Hapus gambar latar dan kembali ke gradient bawaan</label>
                </div>
            @endif
        </div>

        <button type="submit" class="btn-primary">Simpan Perubahan</button>
    </form>

    <script>
        (function () {
            var preview = document.getElementById('bgPreview');
            var fileInput = document.getElementById('login_background');
            var sizeSelect = document.getElementById('login_background_size');
            var scaleField = document.getElementById('scaleField');
            var scaleInput = document.getElementById('login_background_scale');
            var opacityInput = document.getElementById('login_background_opacity');
            var opacityValue = document.getElementById('opacityValue');
            var remove = document.getElementById('remove_login_background');

            // URL objek dari berkas yang baru dipilih; menang atas gambar
            // tersimpan supaya admin bisa menilai sebelum menyimpan.
            var pendingUrl = null;

            function activeUrl() {
                if (remove && remove.checked) return '';
                return pendingUrl || preview.getAttribute('data-url') || '';
            }

            function render() {
                var url = activeUrl();
                var mode = sizeSelect.value;
                var scale = parseInt(scaleInput.value, 10) || 100;
                var opacity = parseInt(opacityInput.value, 10);
                if (isNaN(opacity)) opacity = 100;

                scaleField.style.display = mode === 'custom' ? '' : 'none';
                opacityValue.textContent = opacity;

                if (!url) {
                    preview.style.backgroundImage = '';
                    preview.classList.add('bg-preview-empty');
                    if (!preview.textContent.trim()) {
                        preview.textContent = 'Tidak ada gambar latar.';
                    }
                    return;
                }

                preview.classList.remove('bg-preview-empty');
                preview.textContent = '';

                var size = 'cover', repeat = 'no-repeat';
                if (mode === 'contain') { size = 'contain'; }
                else if (mode === 'stretch') { size = '100% 100%'; }
                else if (mode === 'tile') { size = 'auto'; repeat = 'repeat'; }
                else if (mode === 'custom') { size = scale + '% auto'; }

                var veil = ((100 - opacity) / 100).toFixed(2);
                var styles = getComputedStyle(document.documentElement);
                var rgb = (styles.getPropertyValue('--veil-rgb') || '244,247,244').trim();

                preview.style.backgroundImage =
                    'linear-gradient(rgba(' + rgb + ',' + veil + '), rgba(' + rgb + ',' + veil + ')), url("' + url + '")';
                preview.style.backgroundSize = 'cover, ' + size;
                preview.style.backgroundRepeat = 'no-repeat, ' + repeat;
            }

            fileInput.addEventListener('change', function () {
                if (pendingUrl) URL.revokeObjectURL(pendingUrl);
                pendingUrl = this.files && this.files[0] ? URL.createObjectURL(this.files[0]) : null;
                render();
            });

            sizeSelect.addEventListener('change', render);
            scaleInput.addEventListener('input', render);
            opacityInput.addEventListener('input', render);
            if (remove) remove.addEventListener('change', render);

            render();
        })();
    </script>
@endsection

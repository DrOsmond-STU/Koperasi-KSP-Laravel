@extends('layouts.app')

@section('title', 'Pengaturan Cetakan')

@section('content')
    <style>
        .settings-card {
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 22px; max-width: 520px;
        }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input[type=text], .field input[type=number], .field select {
            width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid var(--line); border-radius: 9px;
        }
        .field-checkbox { display: flex; align-items: center; gap: 8px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .link-alt { color: var(--pine-bright); font-size: 13px; text-decoration: none; display: inline-block; margin-top: 16px; }
    </style>

    <h2>Pengaturan Cetakan</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px;">
        Kertas, margin, dan kop surat ini dipakai bersama oleh semua cetakan (simpanan, pinjaman, bukti kas, jurnal, dsb).
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="settings-card">
        <form method="POST" action="{{ route('admin.pengaturan.cetakan.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="paper_size">Ukuran Kertas</label>
                <select id="paper_size" name="paper_size">
                    <option value="a4" @selected(old('paper_size', $setting->paper_size) === 'a4')>A4</option>
                    <option value="letter" @selected(old('paper_size', $setting->paper_size) === 'letter')>Letter</option>
                    <option value="f4" @selected(old('paper_size', $setting->paper_size) === 'f4')>F4 (Folio)</option>
                </select>
            </div>

            <div class="field">
                <label for="orientation">Orientasi</label>
                <select id="orientation" name="orientation">
                    <option value="portrait" @selected(old('orientation', $setting->orientation) === 'portrait')>Portrait</option>
                    <option value="landscape" @selected(old('orientation', $setting->orientation) === 'landscape')>Landscape</option>
                </select>
            </div>

            <div class="field">
                <label for="margin_mm">Margin (mm)</label>
                <input type="number" id="margin_mm" name="margin_mm" min="5" max="40" value="{{ old('margin_mm', $setting->margin_mm) }}">
                @error('margin_mm') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="font_size_pt">Ukuran Font (pt)</label>
                <input type="number" id="font_size_pt" name="font_size_pt" min="8" max="16" value="{{ old('font_size_pt', $setting->font_size_pt) }}">
                @error('font_size_pt') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label class="field-checkbox">
                    <input type="checkbox" name="show_address" value="1" @checked(old('show_address', $setting->show_address))>
                    Tampilkan alamat di kop surat
                </label>
            </div>

            <div class="field">
                <label for="address_text">Alamat Koperasi</label>
                <input type="text" id="address_text" name="address_text" value="{{ old('address_text', $setting->address_text) }}" placeholder="Jl. Contoh No. 1, Kota">
                @error('address_text') <div class="error-text">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="footer_text">Teks Kaki Halaman (opsional)</label>
                <input type="text" id="footer_text" name="footer_text" value="{{ old('footer_text', $setting->footer_text) }}">
                <p class="hint">Nama & logo koperasi diatur di halaman Pengaturan Branding, bukan di sini.</p>
            </div>

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>

    <a href="{{ route('admin.pengaturan.tanda-tangan.index') }}" class="link-alt">Atur Tanda Tangan Cetakan &rarr;</a>
@endsection

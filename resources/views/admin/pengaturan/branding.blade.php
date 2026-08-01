@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')

@section('content')
    <style>
        .settings-card {
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 22px; max-width: 480px;
        }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input[type=text] { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .current-logo { max-height: 48px; display: block; margin-bottom: 10px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Nama &amp; Logo Aplikasi</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="settings-card">
        <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data">
            @csrf

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

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection

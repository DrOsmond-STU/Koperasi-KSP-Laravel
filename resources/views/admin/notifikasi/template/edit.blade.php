@extends('layouts.app')

@section('title', 'Ubah Template Notifikasi')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 620px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Ubah Template — {{ $template->trigger_type }} ({{ ucfirst($template->channel) }})</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.notifikasi.template.update', $template) }}">
            @csrf
            @method('PUT')

            @if ($template->channel === 'email')
                <div class="field">
                    <label>Subjek Email</label>
                    <input type="text" name="subject" value="{{ old('subject', $template->subject) }}">
                </div>
            @endif
            <div class="field">
                <label>Isi Pesan</label>
                <textarea name="body" rows="6">{{ old('body', $template->body) }}</textarea>
                <p class="hint">Placeholder yang tersedia: {nama_anggota}, {no_pinjaman}, {nominal_angsuran}, {tanggal_jatuh_tempo}, {nama_cabang}, {judul_kegiatan}, {tanggal_kegiatan}.</p>
            </div>
            <div class="field">
                <label><input type="checkbox" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }} style="width:auto;"> Aktif</label>
            </div>

            <button type="submit" class="btn-primary">Simpan Template</button>
        </form>
    </div>
@endsection

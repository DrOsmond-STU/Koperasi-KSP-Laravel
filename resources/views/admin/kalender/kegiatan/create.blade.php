@extends('layouts.app')

@section('title', 'Kegiatan Baru')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 620px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field select[multiple] { height: 120px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Kegiatan Baru</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.kalender.kegiatan.store') }}">
            @csrf

            <div class="field">
                <label>Judul Kegiatan</label>
                <input type="text" name="title" value="{{ old('title') }}" required>
            </div>
            <div class="field">
                <label>Deskripsi</label>
                <textarea name="description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="field">
                <label>Mulai</label>
                <input type="datetime-local" name="start_at" required>
            </div>
            <div class="field">
                <label>Selesai</label>
                <input type="datetime-local" name="end_at" required>
            </div>
            <div class="field">
                <label>Lokasi</label>
                <input type="text" name="location" value="{{ old('location') }}">
            </div>
            <div class="field">
                <label>Cakupan Cabang</label>
                <select name="branch_scope_type" required>
                    <option value="all">Semua Cabang</option>
                    <option value="single">Satu Cabang</option>
                    <option value="multiple">Beberapa Cabang</option>
                </select>
            </div>
            <div class="field">
                <label>Cabang (jika Satu/Beberapa)</label>
                <select name="single_branch_id">
                    <option value="">— Tidak Berlaku —</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                <select name="branch_ids[]" multiple>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                <p class="hint">Dropdown atas untuk "Satu Cabang", daftar bawah (multi-pilih) untuk "Beberapa Cabang".</p>
            </div>
            <div class="field">
                <label>Peserta</label>
                <select name="participant_type" required>
                    <option value="semua_anggota_cabang">Semua Anggota Cabang Terkait</option>
                    <option value="anggota_tertentu">Anggota Tertentu</option>
                    <option value="role_tertentu">Role Tertentu</option>
                </select>
            </div>
            <div class="field">
                <label>Anggota Tertentu (jika dipilih di atas)</label>
                <select name="member_ids[]" multiple>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Role Tertentu (jika dipilih di atas)</label>
                <select name="role_ids[]" multiple>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Pengingat (hari sebelum, pisahkan koma — mis. 3,1,0)</label>
                <input type="text" name="reminder_days_raw" value="{{ old('reminder_days_raw', '3,1,0') }}" placeholder="3,1,0">
            </div>

            <button type="submit" class="btn-primary">Simpan Kegiatan</button>
        </form>
    </div>
@endsection

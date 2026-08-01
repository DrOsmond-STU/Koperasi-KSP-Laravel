@extends('layouts.app')

@section('title', 'Tambah Anggota')

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
    </style>

    <h2>Tambah Anggota</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.members.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label>Foto</label>
                <input type="file" name="photo" accept="image/png,image/jpeg">
            </div>

            <div class="field-row">
                <div class="field">
                    <label>No. Anggota</label>
                    <input type="text" name="member_number" value="{{ old('member_number') }}" required>
                </div>
                <div class="field">
                    <label>Nama</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Jenis Anggota</label>
                    <select name="member_type_id" required>
                        <option value="">— Pilih Jenis —</option>
                        @foreach ($memberTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('member_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cabang</label>
                    <select name="branch_id" required>
                        <option value="">— Pilih Cabang —</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>NIK</label>
                    <input type="text" name="nik" value="{{ old('nik') }}">
                </div>
                <div class="field">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}">
                </div>
            </div>

            <div class="field">
                <label>Alamat</label>
                <input type="text" name="address" value="{{ old('address') }}">
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Status</label>
                    <select name="status" required>
                        @foreach (['calon' => 'Calon', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'keluar' => 'Keluar'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'calon') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tanggal Bergabung</label>
                    <input type="date" name="joined_at" value="{{ old('joined_at') }}">
                </div>
            </div>

            <button type="submit" class="btn-primary">Simpan Anggota</button>
        </form>
    </div>
@endsection

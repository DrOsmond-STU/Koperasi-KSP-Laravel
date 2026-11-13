@extends('layouts.app')

@section('title', 'Import Anggota')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 720px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { display: inline-block; margin-bottom: 16px; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.55; }
        .ref-table { border-collapse: collapse; width: 100%; margin-top: 8px; font-size: 12px; }
        .ref-table th, .ref-table td { border: 1px solid var(--line); padding: 6px 9px; text-align: left; }
        .ref-table th { background: rgba(0,0,0,.03); font-weight: 700; }
        .err-box { background: rgba(0,0,0,.02); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px; max-height: 340px; overflow: auto; }
        .err-box li { margin-bottom: 6px; font-size: 13px; }
        .cols { display: flex; gap: 20px; flex-wrap: wrap; }
        .cols > div { flex: 1; min-width: 240px; }
    </style>

    <h2>Import Anggota</h2>

    <a class="btn-link" href="{{ route('admin.members.index') }}">&larr; Kembali ke Daftar Anggota</a>

    @if (session('error'))
        <div class="error-text" style="margin-bottom: 12px;">{{ session('error') }}</div>
    @endif

    @if (session('status'))
        <div style="margin-bottom: 12px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="err-box">
            <strong>Baris yang gagal ({{ count(session('import_errors')) }}):</strong>
            <ul>
                @foreach (session('import_errors') as $failure)
                    <li>
                        Baris {{ $failure['row'] }} —
                        {{ implode('; ', $failure['errors']) }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.members.import') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label>File CSV</label>
                <input type="file" name="file" accept=".csv,text/csv" required>
                <div class="hint">
                    Kolom wajib berurutan:
                    <code>{{ implode(', ', \App\Services\MemberImportService::HEADERS) }}</code>.
                    Tanggal memakai format <code>YYYY-MM-DD</code>. Status diisi salah satu dari
                    Calon / Aktif / Nonaktif / Keluar.
                    <a href="{{ route('admin.members.import.template') }}">Unduh template CSV</a>.
                </div>
            </div>

            <div class="field">
                <label>Mode</label>
                <select name="mode" required>
                    <option value="all_or_nothing">All-or-nothing — simpan hanya bila tidak ada error</option>
                    <option value="partial">Partial — simpan baris yang valid saja</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Validasi &amp; Import</button>
        </form>
    </div>

    <div class="form-card" style="margin-top: 18px;">
        <strong>Kode referensi</strong>
        <div class="hint">
            Kolom <code>kode_cabang</code> dan <code>kode_jenis_anggota</code> diisi memakai
            <em>kode</em> di bawah ini, bukan angka id.
        </div>

        <div class="cols">
            <div>
                <table class="ref-table">
                    <thead><tr><th>kode_cabang</th><th>Nama Cabang</th></tr></thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr>
                                <td><code>{{ $branch->code }}</code></td>
                                <td>{{ $branch->name }}@unless ($branch->is_active) <em>(nonaktif)</em>@endunless</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">Belum ada cabang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                <table class="ref-table">
                    <thead><tr><th>kode_jenis_anggota</th><th>Jenis Anggota</th></tr></thead>
                    <tbody>
                        @forelse ($memberTypes as $type)
                            <tr>
                                <td><code>{{ $type->code }}</code></td>
                                <td>{{ $type->name }}@unless ($type->is_active) <em>(nonaktif)</em>@endunless</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">Belum ada jenis anggota.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

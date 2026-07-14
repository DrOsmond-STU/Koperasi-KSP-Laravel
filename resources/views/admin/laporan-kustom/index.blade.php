@extends('layouts.app')

@section('title', 'Report Builder')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 640px; margin-bottom: 24px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field select[multiple] { height: 120px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Report Builder</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-text" style="margin-bottom:14px;">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('admin.laporan-kustom.generate') }}">
            @csrf

            <div class="field">
                <label>Jenis Laporan</label>
                <select name="report_type" required>
                    @foreach ($reportTypes as $key => $definition)
                        <option value="{{ $key }}">{{ $definition['label'] }}</option>
                    @endforeach
                </select>
            </div>

            @foreach ($reportTypes as $key => $definition)
                <div class="field">
                    <label>Kolom untuk "{{ $definition['label'] }}"</label>
                    <select name="columns[]" multiple>
                        @foreach ($definition['columns'] as $columnKey => $columnLabel)
                            <option value="{{ $columnKey }}" selected>{{ $columnLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <p class="hint">Pilih hanya kolom untuk jenis laporan yang dipilih di atas — kolom di luar whitelist jenis laporan tersebut akan ditolak server.</p>

            <div class="field">
                <label>Barang (untuk Kartu Persediaan)</label>
                <input type="number" name="filters[product_id]" placeholder="ID Barang">
            </div>
            <div class="field">
                <label>Periode Mulai (untuk Kartu Persediaan)</label>
                <input type="date" name="filters[period_start]">
            </div>
            <div class="field">
                <label>Periode Selesai (untuk Kartu Persediaan)</label>
                <input type="date" name="filters[period_end]">
            </div>
            <div class="field">
                <label>Aktiva Tetap (untuk Kartu Penyusutan)</label>
                <input type="number" name="filters[fixed_asset_id]" placeholder="ID Aktiva Tetap">
            </div>
            <div class="field">
                <label>Simpan sebagai Template (opsional)</label>
                <input type="text" name="save_as_template" placeholder="Nama template">
            </div>
            <div class="field">
                <label>Ekspor (opsional — kosongkan untuk preview di layar)</label>
                <select name="export_format">
                    <option value="">— Preview di Layar —</option>
                    <option value="pdf">PDF</option>
                    <option value="xlsx">Excel (XLSX)</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Generate Laporan</button>
        </form>
    </div>

    <h3>Template Tersimpan</h3>
    <table class="data-table">
        <thead><tr><th>Nama</th><th>Jenis Laporan</th><th>Dibagikan ke Role</th></tr></thead>
        <tbody>
            @forelse ($templates as $template)
                <tr>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->report_type }}</td>
                    <td>{{ $template->sharedRole?->name ?? 'Privat' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Belum ada template tersimpan.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

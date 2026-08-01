@extends('layouts.app')

@section('title', 'Report Builder')

@section('content')
    <style>
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; }
        .form-card h3 { margin-top: 0; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field select[multiple] { height: 120px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
    </style>

    <h2>Report Builder</h2>
    <p class="hint" style="margin-bottom:18px;">Setiap jenis laporan punya form-nya sendiri — kolom dan filter yang tampil hanya yang relevan untuk laporan itu.</p>

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

    <div class="report-grid">
        {{-- Kartu Persediaan (Rincian) --}}
        <div class="form-card">
            <h3>{{ $reportTypes['persediaan_kartu']['label'] }}</h3>
            <form method="POST" action="{{ route('admin.laporan-kustom.generate') }}">
                @csrf
                <input type="hidden" name="report_type" value="persediaan_kartu">

                <div class="field">
                    <label>Kolom</label>
                    <select name="columns[]" multiple>
                        @foreach ($reportTypes['persediaan_kartu']['columns'] as $columnKey => $columnLabel)
                            <option value="{{ $columnKey }}" selected>{{ $columnLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Barang</label>
                    <select name="filters[product_id]" class="js-searchable" required>
                        <option value="">— Pilih Barang —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cabang (opsional — kosongkan untuk semua cabang)</label>
                    <select name="filters[branch_id]" class="js-searchable">
                        <option value="">— Semua Cabang —</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Periode Mulai</label>
                    <input type="date" name="filters[period_start]" required>
                </div>
                <div class="field">
                    <label>Periode Selesai</label>
                    <input type="date" name="filters[period_end]" required>
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

        {{-- Kartu Penyusutan Aktiva Tetap --}}
        <div class="form-card">
            <h3>{{ $reportTypes['aktiva_tetap_kartu_penyusutan']['label'] }}</h3>
            <form method="POST" action="{{ route('admin.laporan-kustom.generate') }}">
                @csrf
                <input type="hidden" name="report_type" value="aktiva_tetap_kartu_penyusutan">

                <div class="field">
                    <label>Kolom</label>
                    <select name="columns[]" multiple>
                        @foreach ($reportTypes['aktiva_tetap_kartu_penyusutan']['columns'] as $columnKey => $columnLabel)
                            <option value="{{ $columnKey }}" selected>{{ $columnLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Aktiva Tetap</label>
                    <select name="filters[fixed_asset_id]" class="js-searchable" required>
                        <option value="">— Pilih Aktiva Tetap —</option>
                        @foreach ($fixedAssets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->code }} — {{ $asset->name }}</option>
                        @endforeach
                    </select>
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

        {{-- Retribusi UPF --}}
        <div class="form-card">
            <h3>{{ $reportTypes['retribusi_upf']['label'] }}</h3>
            <form method="POST" action="{{ route('admin.laporan-kustom.generate') }}">
                @csrf
                <input type="hidden" name="report_type" value="retribusi_upf">

                <div class="field">
                    <label>Kolom</label>
                    <select name="columns[]" multiple>
                        @foreach ($reportTypes['retribusi_upf']['columns'] as $columnKey => $columnLabel)
                            <option value="{{ $columnKey }}" selected>{{ $columnLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cabang (opsional — kosongkan untuk semua cabang)</label>
                    <select name="filters[branch_id]" class="js-searchable">
                        <option value="">— Semua Cabang —</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Periode Mulai (opsional)</label>
                    <input type="date" name="filters[period_start]">
                </div>
                <div class="field">
                    <label>Periode Selesai (opsional)</label>
                    <input type="date" name="filters[period_end]">
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

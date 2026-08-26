@extends('layouts.app')

@section('title', 'Impor Koreksi Tenor & Tarif Pinjaman')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input[type="file"] { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-text { color: var(--brick); font-size: 12px; }
        .summary-row { display: flex; gap: 20px; margin-bottom: 14px; }
        .summary-tile { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 12px 18px; }
        .summary-tile .num { font-size: 22px; font-weight: 700; }
        .summary-tile .label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
    </style>

    <h2>Impor Koreksi Tenor &amp; Tarif Pinjaman</h2>
    <p style="color: var(--muted); margin-top: -8px;">
        Unggah CSV untuk mengoreksi <code>tenor_days</code>, <code>tenor_unit</code>, dan tarif jasa (<code>interest_rate_percentage</code>)
        pada pinjaman yang sudah berjalan — mis. menyamakan data hasil migrasi Saldo Awal dengan buku besar sumber.
        Hanya tiga kolom ini yang berubah; pokok pinjaman, jadwal tagihan, dan jurnal tidak disentuh.
    </p>

    <div class="panel">
        <h3>Format CSV</h3>
        <p style="font-size: 13px; color: var(--muted);">
            Header wajib: <code>loan_number,tenor_days,tenor_unit,rate_percentage</code>.
            <code>tenor_unit</code> harus <code>hari</code> atau <code>bulan</code>.
            <code>rate_percentage</code> adalah tarif flat untuk seluruh tenor (contoh: pinjaman Rp 10.000.000
            dengan total Jasa Rp 500.000 → <code>rate_percentage</code> = 5).
        </p>
        <pre style="background: var(--paper, #f5f5f5); padding: 10px 14px; border-radius: 8px; font-size: 12px; overflow-x: auto;">loan_number,tenor_days,tenor_unit,rate_percentage
117-0151-00305,200,hari,10.0
117-0151-00426,100,hari,5.0</pre>
    </div>

    <div class="panel">
        <form method="POST" action="{{ route('admin.pinjaman.import-tenor-tarif.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>File CSV</label>
                <input type="file" name="file" accept=".csv,text/csv" required>
                @error('file')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn-primary">Proses Impor</button>
        </form>
    </div>

    @if ($updated !== null)
        <div class="panel">
            <h3>Hasil Impor</h3>
            <div class="summary-row">
                <div class="summary-tile">
                    <div class="num">{{ count($updated) }}</div>
                    <div class="label">Berhasil Dikoreksi</div>
                </div>
                <div class="summary-tile">
                    <div class="num">{{ count($rowErrors) }}</div>
                    <div class="label">Dilewati (Error)</div>
                </div>
            </div>

            @if (count($rowErrors) > 0)
                <h4>Baris yang Dilewati</h4>
                <div class="error-text">
                    <ul>
                        @foreach ($rowErrors as $rowError)
                            <li>{{ $rowError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($updated) > 0)
                <h4>Baris yang Dikoreksi</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Pinjaman</th>
                            <th>Tenor Sebelum</th>
                            <th>Tenor Sesudah</th>
                            <th>Tarif Sebelum</th>
                            <th>Tarif Sesudah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($updated as $row)
                            <tr>
                                <td>{{ $row['loan_number'] }}</td>
                                <td>{{ $row['before']['tenor_days'] }} {{ $row['before']['tenor_unit'] }}</td>
                                <td>{{ $row['after']['tenor_days'] }} {{ $row['after']['tenor_unit'] }}</td>
                                <td>{{ $row['before']['interest_rate_percentage'] }}%</td>
                                <td>{{ $row['after']['interest_rate_percentage'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
@endsection

@extends('layouts.app')

@section('title', 'Laporan Keuangan')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .total-row td { font-weight: 700; border-top: 2px solid var(--line); }
        .empty-note { color: var(--muted); font-size: 13px; padding: 14px; text-align: center; border: 1px dashed var(--line); border-radius: 8px; }
        .badge-ok { color: #2E7D52; font-weight: 700; }
        .badge-bad { color: #A8472F; font-weight: 700; }
        .btn-primary { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Laporan Keuangan {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <form class="filter-bar" method="GET">
        <select name="basis" onchange="this.form.submit()">
            <option value="sak_ep" {{ $basis === 'sak_ep' ? 'selected' : '' }}>SAK EP</option>
            <option value="sak_emkm" {{ $basis === 'sak_emkm' ? 'selected' : '' }}>SAK EMKM</option>
        </select>
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <input type="date" name="as_of_date" value="{{ $asOfDate }}" onchange="this.form.submit()">
        <input type="date" name="period_start" value="{{ $periodStart }}" onchange="this.form.submit()">
        <input type="date" name="period_end" value="{{ $periodEnd }}" onchange="this.form.submit()">
    </form>

    <p><a href="{{ route('admin.laporan-keuangan.exports') }}">Lihat Daftar Ekspor Saya</a></p>

    <div class="panel">
        <h3>Neraca ({{ strtoupper(str_replace('_', ' ', $basis)) }})</h3>
        @if (! $neraca['has_data'])
            <p class="empty-note">Tidak ada data neraca untuk tanggal ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Akun</th><th>Saldo</th></tr></thead>
                <tbody>
                    @foreach ($neraca['rows'] as $row)
                        <tr>
                            <td>{{ $row['account']->code }} — {{ $row['account']->name }} {{ $row['eliminated'] ? '(dieliminasi)' : '' }}</td>
                            <td>Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row"><td>Total Aset</td><td>Rp {{ number_format((float) $neraca['total_aset'], 0, ',', '.') }}</td></tr>
                    <tr class="total-row"><td>Total Liabilitas + Ekuitas</td><td>Rp {{ number_format((float) bcadd($neraca['total_liabilitas'], $neraca['total_ekuitas'], 2), 0, ',', '.') }}</td></tr>
                </tbody>
            </table>
            <p class="{{ $neraca['is_balanced'] ? 'badge-ok' : 'badge-bad' }}">{{ $neraca['is_balanced'] ? 'Seimbang' : 'TIDAK SEIMBANG' }}</p>
        @endif
        <form method="POST" action="{{ route('admin.laporan-keuangan.export') }}">
            @csrf
            <input type="hidden" name="report_kind" value="neraca">
            <input type="hidden" name="basis" value="{{ $basis }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
            <input type="hidden" name="as_of_date" value="{{ $asOfDate }}">
            <select name="format"><option value="pdf">PDF</option><option value="xlsx">Excel</option></select>
            <button type="submit" class="btn-primary">Ekspor</button>
        </form>
    </div>

    <div class="panel">
        <h3>Laba Rugi / Perhitungan Hasil Usaha</h3>
        @if (! $labaRugi['has_data'])
            <p class="empty-note">Tidak ada aktivitas pendapatan/beban pada periode ini.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Akun</th><th>Jumlah</th></tr></thead>
                <tbody>
                    @foreach ($labaRugi['rows'] as $row)
                        <tr>
                            <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                            <td>Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row"><td>Total Pendapatan</td><td>Rp {{ number_format((float) $labaRugi['total_pendapatan'], 0, ',', '.') }}</td></tr>
                    <tr class="total-row"><td>Total Beban</td><td>Rp {{ number_format((float) $labaRugi['total_beban'], 0, ',', '.') }}</td></tr>
                    <tr class="total-row"><td>SHU</td><td>Rp {{ number_format((float) $labaRugi['shu'], 0, ',', '.') }}</td></tr>
                </tbody>
            </table>
        @endif
        <form method="POST" action="{{ route('admin.laporan-keuangan.export') }}">
            @csrf
            <input type="hidden" name="report_kind" value="laba_rugi">
            <input type="hidden" name="basis" value="{{ $basis }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
            <input type="hidden" name="period_start" value="{{ $periodStart }}">
            <input type="hidden" name="period_end" value="{{ $periodEnd }}">
            <select name="format"><option value="pdf">PDF</option><option value="xlsx">Excel</option></select>
            <button type="submit" class="btn-primary">Ekspor</button>
        </form>
    </div>

    <div class="panel">
        <h3>Arus Kas</h3>
        @if (! $arusKas['has_data'])
            <p class="empty-note">Tidak ada mutasi kas/bank pada periode ini.</p>
        @else
            <table class="data-table">
                <tbody>
                    <tr><td>Saldo Awal</td><td>Rp {{ number_format((float) $arusKas['opening_balance'], 0, ',', '.') }}</td></tr>
                    <tr><td>Total Masuk</td><td>Rp {{ number_format((float) $arusKas['total_masuk'], 0, ',', '.') }}</td></tr>
                    <tr><td>Total Keluar</td><td>Rp {{ number_format((float) $arusKas['total_keluar'], 0, ',', '.') }}</td></tr>
                    <tr class="total-row"><td>Saldo Akhir</td><td>Rp {{ number_format((float) $arusKas['closing_balance'], 0, ',', '.') }}</td></tr>
                </tbody>
            </table>
            <p class="{{ $arusKas['is_consistent'] ? 'badge-ok' : 'badge-bad' }}">{{ $arusKas['is_consistent'] ? 'Konsisten dengan Buku Besar' : 'TIDAK KONSISTEN' }}</p>
        @endif
        <form method="POST" action="{{ route('admin.laporan-keuangan.export') }}">
            @csrf
            <input type="hidden" name="report_kind" value="arus_kas">
            <input type="hidden" name="basis" value="{{ $basis }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
            <input type="hidden" name="period_start" value="{{ $periodStart }}">
            <input type="hidden" name="period_end" value="{{ $periodEnd }}">
            <select name="format"><option value="pdf">PDF</option><option value="xlsx">Excel</option></select>
            <button type="submit" class="btn-primary">Ekspor</button>
        </form>
    </div>

    <div class="panel">
        <h3>Catatan atas Laporan Keuangan (CALK)</h3>
        <ul>
            @foreach ($calk['kebijakan_akuntansi'] as $policy)
                <li>{{ $policy }}</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.laporan-keuangan.export') }}">
            @csrf
            <input type="hidden" name="report_kind" value="calk">
            <input type="hidden" name="basis" value="{{ $basis }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
            <input type="hidden" name="as_of_date" value="{{ $asOfDate }}">
            <select name="format"><option value="pdf">PDF</option><option value="xlsx">Excel</option></select>
            <button type="submit" class="btn-primary">Ekspor</button>
        </form>
    </div>
@endsection

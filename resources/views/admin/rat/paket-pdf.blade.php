@extends('prints.layout')

@section('title', 'Paket Laporan RAT')

@section('print-content')
    <style>
        h3 { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #D7E2DB; padding: 5px 8px; text-align: left; }
        th { background: #F4F7F4; }
        .amount { text-align: right; }
        .footer { margin-top: 20px; font-size: 9px; color: #777; }
        .section-row td { font-weight: bold; text-transform: uppercase; font-size: 9pt; background: #F4F7F4; }
    </style>

    <h2 style="font-size:13pt; margin:0 0 2px;">Paket Laporan RAT — Tahun Buku {{ $year }}</h2>

    @php
        $aktivaRows = $neraca['rows']->filter(fn ($row) => $row['account']->type === 'ASET');
        $pasivaRows = $neraca['rows']->filter(fn ($row) => $row['account']->type !== 'ASET');
    @endphp
    <h3>Neraca</h3>
    <table>
        <thead><tr><th>Akun</th><th class="amount">Saldo</th></tr></thead>
        <tbody>
            <tr class="section-row"><td colspan="2">Aktiva</td></tr>
            @foreach ($aktivaRows as $row)
                <tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td></tr>
            @endforeach
            <tr><td><strong>Total Aktiva</strong></td><td class="amount"><strong>Rp {{ number_format((float) $neraca['total_aset'], 0, ',', '.') }}</strong></td></tr>

            <tr class="section-row"><td colspan="2">Pasiva</td></tr>
            @foreach ($pasivaRows as $row)
                <tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td></tr>
            @endforeach
            <tr><td><strong>Total Pasiva (Liabilitas + Ekuitas)</strong></td><td class="amount"><strong>Rp {{ number_format((float) bcadd($neraca['total_liabilitas'], $neraca['total_ekuitas'], 2), 0, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <h3>Laba Rugi / Perhitungan Hasil Usaha</h3>
    @php
        $labaRugiPendapatanRows = $labaRugi['rows']->filter(fn ($row) => $row['account']->type === 'PENDAPATAN');
        $labaRugiBebanRows = $labaRugi['rows']->filter(fn ($row) => $row['account']->type === 'BEBAN');
    @endphp
    <table>
        <tbody>
            <tr class="section-row"><td colspan="2">Pendapatan</td></tr>
            @foreach ($labaRugiPendapatanRows as $row)
                <tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="amount">Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td></tr>
            @endforeach
            <tr><td>Total Pendapatan</td><td class="amount">Rp {{ number_format((float) $labaRugi['total_pendapatan'], 0, ',', '.') }}</td></tr>

            <tr class="section-row"><td colspan="2">Beban</td></tr>
            @foreach ($labaRugiBebanRows as $row)
                <tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="amount">Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td></tr>
            @endforeach
            <tr><td>Total Beban</td><td class="amount">Rp {{ number_format((float) $labaRugi['total_beban'], 0, ',', '.') }}</td></tr>

            <tr><td><strong>SHU (Pendapatan − Beban)</strong></td><td class="amount"><strong>Rp {{ number_format((float) $labaRugi['shu'], 0, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <h3>Simulasi Alokasi SHU</h3>
    <table>
        <thead><tr><th>Kategori</th><th class="amount">Nominal</th></tr></thead>
        <tbody>
            @foreach ($shu['allocations'] as $allocation)
                <tr><td>{{ $allocation['category']->name }} ({{ $allocation['category']->percentage }}%)</td><td class="amount">Rp {{ number_format((float) $allocation['amount'], 0, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h3>Checklist Kesiapan RAT</h3>
    <table>
        <tbody>
            @foreach ($ratSummary['readiness_checklist'] as $item => $ready)
                <tr><td>{{ $item }}</td><td>{{ $ready ? 'Siap' : 'Belum' }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p>Total Anggota: {{ $ratSummary['total_members'] }}</p>

    <p class="footer">Dibuat oleh {{ $requestedByName }} pada {{ $requestedAt }}</p>
@endsection

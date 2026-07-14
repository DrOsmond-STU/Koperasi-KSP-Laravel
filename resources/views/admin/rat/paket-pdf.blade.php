<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h2 { margin-bottom: 2px; }
        h3 { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .amount { text-align: right; }
        .footer { margin-top: 20px; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    <h2>Paket Laporan RAT — Tahun Buku {{ $year }}</h2>
    <p>{{ config('app.name') }}</p>

    <h3>Neraca</h3>
    <table>
        <thead><tr><th>Akun</th><th class="amount">Saldo</th></tr></thead>
        <tbody>
            @foreach ($neraca['rows'] as $row)
                <tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td></tr>
            @endforeach
            <tr><td><strong>Total Aset</strong></td><td class="amount"><strong>Rp {{ number_format((float) $neraca['total_aset'], 0, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <h3>Laba Rugi / Perhitungan Hasil Usaha</h3>
    <table>
        <tbody>
            <tr><td>Total Pendapatan</td><td class="amount">Rp {{ number_format((float) $labaRugi['total_pendapatan'], 0, ',', '.') }}</td></tr>
            <tr><td>Total Beban</td><td class="amount">Rp {{ number_format((float) $labaRugi['total_beban'], 0, ',', '.') }}</td></tr>
            <tr><td><strong>SHU</strong></td><td class="amount"><strong>Rp {{ number_format((float) $labaRugi['shu'], 0, ',', '.') }}</strong></td></tr>
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
</body>
</html>

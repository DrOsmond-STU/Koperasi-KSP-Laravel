<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h2 { margin-bottom: 2px; }
        .meta { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .amount { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #333; }
        .footer { margin-top: 20px; font-size: 9px; color: #777; }
        .empty-state { padding: 20px; text-align: center; color: #888; border: 1px dashed #ccc; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <p class="meta">
        {{ config('app.name') }} — {{ $data['is_consolidated'] ?? false ? 'Konsolidasi Seluruh Cabang' : ($branchName ?? '') }}
        @if (isset($data['as_of_date'])) — per {{ \Illuminate\Support\Carbon::parse($data['as_of_date'])->translatedFormat('d M Y') }} @endif
        @if (isset($data['period_start'])) — periode {{ \Illuminate\Support\Carbon::parse($data['period_start'])->translatedFormat('d M Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($data['period_end'])->translatedFormat('d M Y') }} @endif
    </p>

    @if (($data['has_data'] ?? true) === false)
        <div class="empty-state">Tidak ada data untuk periode/tanggal ini.</div>
    @elseif ($reportKind === 'neraca')
        <table>
            <thead><tr><th>Akun</th><th class="amount">Saldo</th></tr></thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}{{ $row['eliminated'] ? ' (dieliminasi - RAK)' : '' }}</td>
                        <td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row"><td>Total Aset</td><td class="amount">Rp {{ number_format((float) $data['total_aset'], 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td>Total Liabilitas + Ekuitas</td><td class="amount">Rp {{ number_format((float) bcadd($data['total_liabilitas'], $data['total_ekuitas'], 2), 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    @elseif ($reportKind === 'laba_rugi')
        <table>
            <thead><tr><th>Akun</th><th class="amount">Jumlah</th></tr></thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                        <td class="amount">Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row"><td>Total Pendapatan</td><td class="amount">Rp {{ number_format((float) $data['total_pendapatan'], 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td>Total Beban</td><td class="amount">Rp {{ number_format((float) $data['total_beban'], 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td>Sisa Hasil Usaha (SHU)</td><td class="amount">Rp {{ number_format((float) $data['shu'], 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    @elseif ($reportKind === 'arus_kas')
        <table>
            <tbody>
                <tr><td>Saldo Awal Kas/Bank</td><td class="amount">Rp {{ number_format((float) $data['opening_balance'], 0, ',', '.') }}</td></tr>
                <tr><td>Total Kas Masuk</td><td class="amount">Rp {{ number_format((float) $data['total_masuk'], 0, ',', '.') }}</td></tr>
                <tr><td>Total Kas Keluar</td><td class="amount">Rp {{ number_format((float) $data['total_keluar'], 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td>Saldo Akhir Kas/Bank</td><td class="amount">Rp {{ number_format((float) $data['closing_balance'], 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    @elseif ($reportKind === 'calk')
        <h3>Kebijakan Akuntansi</h3>
        <ul>
            @foreach ($data['kebijakan_akuntansi'] as $policy)
                <li>{{ $policy }}</li>
            @endforeach
        </ul>
        @foreach ($data['groups'] as $groupName => $rows)
            <h3>{{ $groupName }}</h3>
            <table>
                <thead><tr><th>Akun</th><th class="amount">Saldo</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                            <td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    <p class="footer">Dibuat oleh {{ $requestedByName }} pada {{ $requestedAt }}</p>
</body>
</html>

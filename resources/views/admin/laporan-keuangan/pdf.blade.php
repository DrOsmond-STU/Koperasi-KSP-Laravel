@extends('prints.layout')

@section('title', $title)

@section('print-content')
    <style>
        .meta { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #D7E2DB; padding: 5px 8px; text-align: left; }
        th { background: #F4F7F4; }
        .amount { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #333; }
        .section-row td { font-weight: bold; text-transform: uppercase; font-size: 9pt; background: #F4F7F4; }
        .footer { margin-top: 20px; font-size: 9px; color: #777; }
        .empty-state { padding: 20px; text-align: center; color: #888; border: 1px dashed #ccc; }
    </style>

    <h2 style="font-size:13pt; margin:0 0 2px;">{{ $title }}</h2>
    <p class="meta">
        {{ $data['is_consolidated'] ?? false ? 'Konsolidasi Seluruh Cabang' : ($branchName ?? '') }}
        @if (isset($data['as_of_date'])) — per {{ \Illuminate\Support\Carbon::parse($data['as_of_date'])->translatedFormat('d M Y') }} @endif
        @if (isset($data['period_start'])) — periode {{ \Illuminate\Support\Carbon::parse($data['period_start'])->translatedFormat('d M Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($data['period_end'])->translatedFormat('d M Y') }} @endif
    </p>

    @if (($data['has_data'] ?? true) === false)
        <div class="empty-state">Tidak ada data untuk periode/tanggal ini.</div>
    @elseif ($reportKind === 'neraca')
        @php
            $aktivaRows = $data['rows']->filter(fn ($row) => $row['account']->type === 'ASET');
            $pasivaRows = $data['rows']->filter(fn ($row) => $row['account']->type !== 'ASET');
        @endphp
        <table>
            <thead><tr><th>Akun</th><th class="amount">Saldo</th></tr></thead>
            <tbody>
                <tr class="section-row"><td colspan="2">Aktiva</td></tr>
                @foreach ($aktivaRows as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}{{ $row['eliminated'] ? ' (dieliminasi - RAK)' : '' }}</td>
                        <td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row"><td>Total Aktiva</td><td class="amount">Rp {{ number_format((float) $data['total_aset'], 0, ',', '.') }}</td></tr>

                <tr class="section-row"><td colspan="2">Pasiva</td></tr>
                @foreach ($pasivaRows as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}{{ $row['eliminated'] ? ' (dieliminasi - RAK)' : '' }}</td>
                        <td class="amount">Rp {{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row"><td>Total Pasiva (Liabilitas + Ekuitas)</td><td class="amount">Rp {{ number_format((float) bcadd($data['total_liabilitas'], $data['total_ekuitas'], 2), 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
    @elseif ($reportKind === 'laba_rugi')
        @php
            $pendapatanRows = $data['rows']->filter(fn ($row) => $row['account']->type === 'PENDAPATAN');
            $bebanRows = $data['rows']->filter(fn ($row) => $row['account']->type === 'BEBAN');
        @endphp
        <table>
            <thead><tr><th>Akun</th><th class="amount">Jumlah</th></tr></thead>
            <tbody>
                <tr class="section-row"><td colspan="2">Pendapatan</td></tr>
                @foreach ($pendapatanRows as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                        <td class="amount">Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row"><td>Total Pendapatan</td><td class="amount">Rp {{ number_format((float) $data['total_pendapatan'], 0, ',', '.') }}</td></tr>

                <tr class="section-row"><td colspan="2">Beban</td></tr>
                @foreach ($bebanRows as $row)
                    <tr>
                        <td>{{ $row['account']->code }} — {{ $row['account']->name }}</td>
                        <td class="amount">Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
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
@endsection

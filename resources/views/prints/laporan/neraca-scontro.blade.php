@extends('prints.layout')

@section('title', 'Neraca (Bentuk Skontro)')

@section('print-content')
    @php
        // Pisahkan baris menurut sisi neraca. Sisi kiri = AKTIVA (ASET),
        // sisi kanan = PASIVA (LIABILITAS + EKUITAS). Kedua sisi disusun
        // berdampingan (bentuk skontro/horizontal/T-form) — kebalikan dari
        // bentuk stafel (vertikal) yang dipakai admin.laporan-keuangan.pdf.
        $aktivaRows = $data['rows']->filter(fn ($row) => $row['account']->type === 'ASET')->values();
        $liabilitasRows = $data['rows']->filter(fn ($row) => $row['account']->type === 'LIABILITAS')->values();
        $ekuitasRows = $data['rows']->filter(fn ($row) => $row['account']->type === 'EKUITAS')->values();

        // Rakit sisi PASIVA sebagai daftar tersegmentasi: header LIABILITAS
        // → baris-baris liabilitas → header EKUITAS → baris-baris ekuitas.
        // Struktur ini dipakai untuk padding baris kosong agar tinggi
        // kolom kiri & kanan seimbang tanpa memerlukan mesin layout multi
        // kolom (dompdf tidak mendukung CSS `column-count` dengan baik).
        $pasivaLines = [];
        $pasivaLines[] = ['kind' => 'section', 'label' => 'LIABILITAS'];
        foreach ($liabilitasRows as $row) {
            $pasivaLines[] = ['kind' => 'row', 'row' => $row];
        }
        $pasivaLines[] = ['kind' => 'subtotal', 'label' => 'Sub-total Liabilitas', 'amount' => $data['total_liabilitas']];
        $pasivaLines[] = ['kind' => 'section', 'label' => 'EKUITAS'];
        foreach ($ekuitasRows as $row) {
            $pasivaLines[] = ['kind' => 'row', 'row' => $row];
        }
        $pasivaLines[] = ['kind' => 'subtotal', 'label' => 'Sub-total Ekuitas', 'amount' => $data['total_ekuitas']];

        // Sisi AKTIVA: satu header + baris-baris aset.
        $aktivaLines = [];
        $aktivaLines[] = ['kind' => 'section', 'label' => 'AKTIVA'];
        foreach ($aktivaRows as $row) {
            $aktivaLines[] = ['kind' => 'row', 'row' => $row];
        }

        // Padding agar tinggi kolom sama — baris kosong ditambahkan pada
        // sisi yang lebih pendek.
        $maxRows = max(count($aktivaLines), count($pasivaLines));
        while (count($aktivaLines) < $maxRows) {
            $aktivaLines[] = ['kind' => 'blank'];
        }
        while (count($pasivaLines) < $maxRows) {
            $pasivaLines[] = ['kind' => 'blank'];
        }

        $totalPasiva = bcadd($data['total_liabilitas'], $data['total_ekuitas'], 2);
        $asOf = \Illuminate\Support\Carbon::parse($data['as_of_date'])->translatedFormat('d M Y');
    @endphp

    <style>
        .neraca-title { text-align: center; margin: 0 0 2px; font-size: 14pt; letter-spacing: 0.5px; }
        .neraca-meta { text-align: center; margin: 0 0 12px; font-size: 9pt; color: #5C6E64; }
        .scontro { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .scontro > tbody > tr > td { vertical-align: top; padding: 0; }
        .side { width: 50%; }
        .side-inner { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .side-inner th, .side-inner td { border: 1px solid #D7E2DB; padding: 4px 6px; }
        .side-inner thead th { background: #11543B; color: #fff; text-align: center; font-weight: 700; letter-spacing: 0.4px; }
        .side-inner .col-akun { width: 65%; }
        .side-inner .col-amount { width: 35%; text-align: right; font-variant-numeric: tabular-nums; }
        .row-section td { background: #F4F7F4; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; font-size: 8.5pt; color: #11543B; }
        .row-subtotal td { background: #FBFBF7; font-weight: 700; font-style: italic; color: #3D5A4B; }
        .row-blank td { border-color: #E9EEEA; color: transparent; }
        .row-blank td:before { content: "\00a0"; }
        .row-total td { background: #11543B; color: #fff; font-weight: 700; font-size: 10pt; letter-spacing: 0.4px; }
        .balance-note { margin-top: 8px; font-size: 8.5pt; text-align: center; color: #5C6E64; }
        .balance-note.warn { color: #A63A2F; font-weight: 700; }
    </style>

    <h2 class="neraca-title">NERACA — BENTUK SKONTRO</h2>
    <p class="neraca-meta">
        {{ ($data['is_consolidated'] ?? false) ? 'Konsolidasi Seluruh Cabang' : ($branchName ?? 'Cabang') }}
        &middot; per <strong>{{ $asOf }}</strong>
        &middot; Basis: <strong>{{ strtoupper(str_replace('_', ' ', $data['basis'] ?? 'sak_ep')) }}</strong>
    </p>

    @if (($data['has_data'] ?? true) === false)
        <p style="text-align:center; padding:20px; border:1px dashed #D7E2DB; color:#5C6E64;">
            Tidak ada data neraca untuk tanggal ini.
        </p>
    @else
        {{-- Bentuk skontro: dua kolom sisi-sisi. Kolom kiri AKTIVA,
             kolom kanan PASIVA (LIABILITAS + EKUITAS). --}}
        <table class="scontro">
            <tbody>
                <tr>
                    {{-- ============ KOLOM KIRI: AKTIVA ============ --}}
                    <td class="side" style="padding-right:6px;">
                        <table class="side-inner">
                            <thead>
                                <tr>
                                    <th colspan="2">AKTIVA</th>
                                </tr>
                                <tr>
                                    <th class="col-akun" style="text-align:left; background:#F4F7F4; color:#11543B;">Akun</th>
                                    <th class="col-amount" style="background:#F4F7F4; color:#11543B;">Saldo (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($aktivaLines as $line)
                                    @switch($line['kind'])
                                        @case('section')
                                            {{-- header sudah di thead; sisa 'section' pada kolom aktiva tidak dipakai --}}
                                            @break
                                        @case('row')
                                            @php $row = $line['row']; @endphp
                                            <tr>
                                                <td>
                                                    {{ $row['account']->code }} &mdash; {{ $row['account']->name }}
                                                    @if ($row['eliminated'])
                                                        <span style="color:#9AA9A0; font-style:italic;">(dieliminasi RAK)</span>
                                                    @endif
                                                </td>
                                                <td class="col-amount">{{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                                            </tr>
                                            @break
                                        @case('blank')
                                            <tr class="row-blank"><td></td><td class="col-amount"></td></tr>
                                            @break
                                    @endswitch
                                @endforeach
                                <tr class="row-total">
                                    <td>TOTAL AKTIVA</td>
                                    <td class="col-amount">{{ number_format((float) $data['total_aset'], 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    {{-- ============ KOLOM KANAN: PASIVA ============ --}}
                    <td class="side" style="padding-left:6px;">
                        <table class="side-inner">
                            <thead>
                                <tr>
                                    <th colspan="2">PASIVA (LIABILITAS + EKUITAS)</th>
                                </tr>
                                <tr>
                                    <th class="col-akun" style="text-align:left; background:#F4F7F4; color:#11543B;">Akun</th>
                                    <th class="col-amount" style="background:#F4F7F4; color:#11543B;">Saldo (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pasivaLines as $line)
                                    @switch($line['kind'])
                                        @case('section')
                                            <tr class="row-section"><td colspan="2">{{ $line['label'] }}</td></tr>
                                            @break
                                        @case('row')
                                            @php $row = $line['row']; @endphp
                                            <tr>
                                                <td>
                                                    {{ $row['account']->code }} &mdash; {{ $row['account']->name }}
                                                    @if ($row['eliminated'])
                                                        <span style="color:#9AA9A0; font-style:italic;">(dieliminasi RAK)</span>
                                                    @endif
                                                </td>
                                                <td class="col-amount">{{ number_format((float) $row['balance'], 0, ',', '.') }}</td>
                                            </tr>
                                            @break
                                        @case('subtotal')
                                            <tr class="row-subtotal">
                                                <td>{{ $line['label'] }}</td>
                                                <td class="col-amount">{{ number_format((float) $line['amount'], 0, ',', '.') }}</td>
                                            </tr>
                                            @break
                                        @case('blank')
                                            <tr class="row-blank"><td></td><td class="col-amount"></td></tr>
                                            @break
                                    @endswitch
                                @endforeach
                                <tr class="row-total">
                                    <td>TOTAL PASIVA</td>
                                    <td class="col-amount">{{ number_format((float) $totalPasiva, 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        @php $isBalanced = $data['is_balanced'] ?? (bccomp((string) $data['total_aset'], (string) $totalPasiva, 2) === 0); @endphp
        <p class="balance-note {{ $isBalanced ? '' : 'warn' }}">
            @if ($isBalanced)
                &#10003; Neraca SEIMBANG &mdash; Total Aktiva = Total Pasiva = Rp {{ number_format((float) $data['total_aset'], 0, ',', '.') }}
            @else
                &#9888; NERACA TIDAK SEIMBANG &mdash;
                Aktiva Rp {{ number_format((float) $data['total_aset'], 0, ',', '.') }} vs
                Pasiva Rp {{ number_format((float) $totalPasiva, 0, ',', '.') }}
                (selisih Rp {{ number_format(abs((float) bcsub((string) $data['total_aset'], (string) $totalPasiva, 2)), 0, ',', '.') }})
            @endif
        </p>
    @endif

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_keuangan'])
@endsection

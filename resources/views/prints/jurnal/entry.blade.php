@extends('prints.layout')

@section('title', 'Jurnal Umum')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">BUKTI JURNAL UMUM</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. Jurnal #{{ $entry->id }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Tanggal</td><td style="padding:3px 0;">: {{ $entry->entry_date->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Cabang</td><td style="padding:3px 0;">: {{ $entry->branch->name }}</td></tr>
        <tr><td style="padding:3px 0;">Keterangan</td><td style="padding:3px 0;">: {{ $entry->description }}</td></tr>
        <tr><td style="padding:3px 0;">Penginput</td><td style="padding:3px 0;">: {{ $entry->createdBy->name }}</td></tr>
    </table>

    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr><th>Kode Akun</th><th>Nama Akun</th><th style="text-align:right;">Debit</th><th style="text-align:right;">Kredit</th></tr>
        </thead>
        <tbody>
            @foreach ($entry->lines as $line)
                <tr>
                    <td>{{ $line->account->code }}</td>
                    <td>{{ $line->account->name }}</td>
                    <td style="text-align:right;">{{ $line->debit > 0 ? number_format($line->debit, 0, ',', '.') : '-' }}</td>
                    <td style="text-align:right;">{{ $line->credit > 0 ? number_format($line->credit, 0, ',', '.') : '-' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" style="text-align:right; font-weight:700;">Total</td>
                <td style="text-align:right; font-weight:700;">{{ number_format($entry->lines->sum('debit'), 0, ',', '.') }}</td>
                <td style="text-align:right; font-weight:700;">{{ number_format($entry->lines->sum('credit'), 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'jurnal_umum',
        'extraSigners' => [['label' => 'Penginput', 'name' => $entry->createdBy->name]],
    ])
@endsection

@extends('prints.layout')

@section('title', 'Pengajuan Pinjaman')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px; text-align:center;">SURAT PENGAJUAN PINJAMAN</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px; text-align:center;">No. {{ $loan->loan_number }}</p>

    <table style="margin-bottom:16px;">
        <tr><td style="width:160px; padding:3px 0;">Nama Anggota</td><td style="padding:3px 0;">: {{ $loan->member->name }}</td></tr>
        <tr><td style="padding:3px 0;">No. Anggota</td><td style="padding:3px 0;">: {{ $loan->member->member_number }}</td></tr>
        <tr><td style="padding:3px 0;">Produk Pinjaman</td><td style="padding:3px 0;">: {{ $loan->loanProduct->name }}</td></tr>
        <tr><td style="padding:3px 0;">Plafon Diajukan</td><td style="padding:3px 0;">: Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td></tr>
        <tr><td style="padding:3px 0;">Tenor</td><td style="padding:3px 0;">: {{ $loan->tenor_months }} bulan</td></tr>
        <tr><td style="padding:3px 0;">Tarif Jasa</td><td style="padding:3px 0;">: {{ $loan->interest_rate_percentage }}% / tahun</td></tr>
        <tr><td style="padding:3px 0;">Tanggal Pengajuan</td><td style="padding:3px 0;">: {{ optional($loan->submitted_at)->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: {{ ucfirst($loan->status) }}</td></tr>
    </table>

    <h3 style="font-size:11pt; margin:0 0 6px;">Riwayat Persetujuan</h3>
    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr><th>Penyetuju</th><th>Keputusan</th><th>Tanggal</th><th>Catatan</th></tr>
        </thead>
        <tbody>
            @forelse ($loan->approvals as $approval)
                <tr>
                    <td>{{ $approval->approvedBy->name }}</td>
                    <td>{{ $approval->decision === 'setuju' ? 'Setuju' : 'Tolak' }}</td>
                    <td>{{ $approval->decided_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $approval->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada keputusan.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('prints.partials.signature-block', [
        'documentGroup' => 'pengajuan_pinjaman',
        'extraSigners' => [['label' => 'Pemohon', 'name' => $loan->member->name]],
    ])
@endsection

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
        <tr><td style="padding:3px 0;">Tenor</td><td style="padding:3px 0;">: {{ $loan->tenorLabel() }}</td></tr>
        <tr><td style="padding:3px 0;">Tarif Jasa</td><td style="padding:3px 0;">: {{ $loan->interest_rate_percentage }}% {{ $loan->usesDailyTenor() ? 'flat (seluruh tenor)' : '/ tahun' }}</td></tr>
        <tr><td style="padding:3px 0;">Tanggal Pengajuan</td><td style="padding:3px 0;">: {{ optional($loan->submitted_at)->translatedFormat('d M Y') }}</td></tr>
        <tr><td style="padding:3px 0;">Status</td><td style="padding:3px 0;">: {{ ucfirst($loan->status) }}</td></tr>
    </table>

    {{-- Tanpa blok ini surat hanya menulis "Dibatalkan" satu kata, lalu
         menyusul tabel berisi "Setuju, Setuju" — terbaca seolah pinjamannya
         masih disetujui. Persetujuannya memang benar terjadi; yang hilang
         justru ceritanya bahwa pembatalan datang SESUDAH itu. --}}
    @if ($loan->isCancelled())
        <div style="border:1.5pt solid #8C2F2F; padding:8px 10px; margin-bottom:16px;">
            <p style="font-size:11pt; font-weight:bold; color:#8C2F2F; margin:0 0 6px;">PENGAJUAN DIBATALKAN</p>
            <table style="font-size:9.5pt;">
                <tr>
                    <td style="width:150px; padding:2px 0;">Tanggal pembatalan</td>
                    <td style="padding:2px 0;">: {{ $loan->cancelled_at->translatedFormat('d M Y H:i') }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 0;">Dibatalkan oleh</td>
                    <td style="padding:2px 0;">: {{ $loan->cancelledBy->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 0; vertical-align:top;">Alasan</td>
                    <td style="padding:2px 0;">: {{ $loan->cancellation_reason ?? '-' }}</td>
                </tr>
                @if ($loan->disbursed_at)
                    <tr>
                        <td style="padding:2px 0; vertical-align:top;">Pencairan</td>
                        <td style="padding:2px 0;">
                            : Sempat dicairkan {{ $loan->disbursed_at->translatedFormat('d M Y') }}, lalu
                            dibalik dengan jurnal koreksi{{ $loan->reversal_journal_entry_id ? ' #'.$loan->reversal_journal_entry_id : '' }}.
                            Tidak ada dana yang tersisa di tangan anggota.
                        </td>
                    </tr>
                @else
                    <tr>
                        <td style="padding:2px 0;">Pencairan</td>
                        <td style="padding:2px 0;">: Tidak pernah dicairkan — tidak ada jurnal atas pengajuan ini.</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    <h3 style="font-size:11pt; margin:0 0 6px;">Riwayat Persetujuan</h3>
    @if ($loan->isCancelled() && $loan->approvals->isNotEmpty())
        <p style="font-size:9pt; color:#5C6E64; margin:0 0 6px;">
            Keputusan di bawah ini diambil <strong>sebelum</strong> pembatalan dan sudah tidak berlaku.
            Dicantumkan sebagai catatan audit, bukan sebagai persetujuan yang masih hidup.
        </p>
    @endif
    <table class="data-table" style="margin-bottom: 10px;">
        <thead>
            <tr><th>Penyetuju</th><th>Keputusan</th><th>Tanggal</th><th>Catatan</th></tr>
        </thead>
        <tbody>
            @forelse ($loan->approvals as $approval)
                <tr>
                    <td>{{ $approval->approvedBy->name }}</td>
                    {{-- Kata "Setuju" berdiri sendiri di kolom ini justru yang
                         paling mudah salah dibaca pada pinjaman yang batal. --}}
                    <td>
                        {{ $approval->decision === 'setuju' ? 'Setuju' : 'Tolak' }}
                        @if ($loan->isCancelled())
                            <span style="color:#8C2F2F;">(tidak berlaku)</span>
                        @endif
                    </td>
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

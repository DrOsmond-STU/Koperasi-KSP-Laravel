@extends('prints.layout')

@section('title', 'Laporan Pinjaman Anggota')

{{--
    Laporan Pinjaman per Anggota — informasi pinjaman + saldo outstanding,
    dengan historis pembayaran (LoanRepayment) sebagai lampiran di bawah
    tiap pinjaman. Outstanding dihitung dari schedules (bukan dari
    balance_after pembayaran terakhir) supaya tetap benar walau ada
    pembayaran yang dibatalkan atau pinjaman belum pernah dibayar sama
    sekali — sum(principal_amount)-sum(paid_principal_amount) selalu
    mencerminakan sisa riil di jadwal, terlepas dari riwayat repayment-nya.
--}}
@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Laporan Pinjaman Anggota</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px;">{{ $filterDescription }}</p>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    @forelse ($members as $member)
        <h3 style="font-size:11pt; margin:0 0 6px; padding-top:6px; border-top:1px solid #D7E2DB;">
            {{ $member->member_number }} — {{ $member->name }}
        </h3>

        @forelse ($member->loans as $loan)
            @php
                $sisaPokok = (float) $loan->schedules->sum('principal_amount') - (float) $loan->schedules->sum('paid_principal_amount');
                $sisaJasa = (float) $loan->schedules->sum('interest_amount') - (float) $loan->schedules->sum('paid_interest_amount');
            @endphp
            <table class="data-table" style="margin-bottom: 4px;">
                <thead>
                    <tr>
                        <th>No. Pinjaman</th>
                        <th>Produk</th>
                        <th>Plafon</th>
                        <th>Tenor</th>
                        <th>Status</th>
                        <th>Tgl Cair</th>
                        <th>Sisa Pokok</th>
                        <th>Sisa Jasa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $loan->loan_number }}</td>
                        <td>{{ $loan->loanProduct->name }}</td>
                        <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                        <td>{{ $loan->tenor_months }} bulan</td>
                        <td>{{ ucfirst($loan->status) }}</td>
                        <td>{{ optional($loan->disbursed_at)->format('d/m/Y') ?? '-' }}</td>
                        <td>Rp {{ number_format($sisaPokok, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($sisaJasa, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Lampiran: historis pembayaran pinjaman ini, lengkap dari awal. --}}
            <table class="data-table" style="margin-bottom: 16px; font-size: 0.92em;">
                <thead>
                    <tr><th colspan="5" style="font-weight:400; font-style:italic;">Historis Pembayaran — {{ $loan->loan_number }}</th></tr>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nominal Bayar</th>
                        <th>Porsi Pokok</th>
                        <th>Porsi Jasa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loan->repayments as $repayment)
                        <tr>
                            <td>{{ $repayment->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>Rp {{ number_format($repayment->amount, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($repayment->principal_portion, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($repayment->interest_portion, 0, ',', '.') }}</td>
                            <td>{{ $repayment->isCancelled() ? 'Dibatalkan' : 'Normal' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada pembayaran angsuran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @empty
            <p style="font-size:9pt; color:#5C6E64; margin:0 0 16px;">Belum ada pinjaman.</p>
        @endforelse
    @empty
        <p>Tidak ada anggota yang cocok dengan saringan ini.</p>
    @endforelse
@endsection

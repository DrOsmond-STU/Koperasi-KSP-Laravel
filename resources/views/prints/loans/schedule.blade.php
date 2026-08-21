@extends('prints.layout')

@section('title', 'Jadwal & Historis Angsuran')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Jadwal &amp; Historis Angsuran — {{ $loan->loan_number }}</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px;">
        {{ $loan->member->member_number }} — {{ $loan->member->name }} | {{ $loan->loanProduct->name }} |
        Plafon Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}, tenor {{ $loan->tenor_days }} hari
    </p>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    <h3 style="font-size:11pt; margin:0 0 6px;">Jadwal Angsuran</h3>
    <table class="data-table" style="margin-bottom: 18px;">
        <thead>
            <tr>
                <th>Angsuran ke-</th>
                <th>Jatuh Tempo</th>
                <th>Pokok</th>
                <th>Jasa</th>
                <th>Total</th>
                <th>Terbayar</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($loan->schedules as $schedule)
                <tr>
                    <td>{{ $schedule->installment_number }}</td>
                    <td>{{ $schedule->due_date->translatedFormat('d M Y') }}</td>
                    <td>Rp {{ number_format($schedule->principal_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($schedule->interest_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($schedule->total_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($schedule->paid_amount, 0, ',', '.') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $schedule->status)) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Jadwal angsuran belum tersedia.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="font-size:11pt; margin:0 0 6px;">Historis Pembayaran</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nominal Bayar</th>
                <th>Porsi Pokok</th>
                <th>Porsi Jasa</th>
                <th>Sisa Tunggakan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($repayments as $repayment)
                <tr>
                    <td>{{ $repayment->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>Rp {{ number_format($repayment->amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($repayment->principal_portion, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($repayment->interest_portion, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($repayment->balance_after, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada pembayaran angsuran.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

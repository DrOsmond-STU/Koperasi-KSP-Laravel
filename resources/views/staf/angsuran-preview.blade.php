@extends('layouts.app')

@section('title', 'Rincian Alokasi Angsuran')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 620px; }
        .alloc-table { width: 100%; border-collapse: collapse; margin: 14px 0; }
        .alloc-table th, .alloc-table td { text-align: right; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .alloc-table th:first-child, .alloc-table td:first-child { text-align: left; }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
        .summary-row.total { font-weight: 700; border-top: 1px solid var(--line); margin-top: 6px; padding-top: 8px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-ghost { padding: 10px 18px; background: transparent; color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; text-decoration: none; display: inline-block; }
    </style>

    <h2>Konfirmasi Angsuran — {{ $loan->loan_number }}</h2>

    <div class="panel">
        <p>Anggota: <strong>{{ $loan->member->name }}</strong></p>
        <p>Tanggal Bayar: <strong>{{ \Illuminate\Support\Carbon::parse($paidAt)->translatedFormat('d M Y') }}</strong></p>
        <p>Akun Kas Penerima: <strong>@if ($cashAccount) {{ $cashAccount->code }} — {{ $cashAccount->name }} @else (tidak ditemukan) @endif</strong></p>

        <table class="alloc-table">
            <thead><tr><th>Komponen</th><th>Nominal</th></tr></thead>
            <tbody>
                <tr><td>Angsuran Pokok</td><td>Rp {{ number_format($principalPortion, 0, ',', '.') }}</td></tr>
                <tr><td>Jasa</td><td>Rp {{ number_format($interestPortion, 0, ',', '.') }}</td></tr>
                <tr><td>Denda</td><td>Rp {{ number_format($penaltyPortion, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>

        <h4 style="margin:18px 0 6px;font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;">Jurnal</h4>
        <table class="alloc-table">
            <thead><tr><th>Akun (COA)</th><th>Debit</th><th>Kredit</th></tr></thead>
            <tbody>
                @foreach ($journalLines as $line)
                    <tr>
                        <td>
                            @if ($line['account'])
                                {{ $line['account']->code }} — {{ $line['account']->name }}
                            @else
                                <span style="color:#b3261e;">(akun belum diatur pada Produk Pinjaman)</span>
                            @endif
                        </td>
                        <td>{{ $line['debit'] > 0 ? 'Rp '.number_format($line['debit'], 0, ',', '.') : '—' }}</td>
                        <td>{{ $line['credit'] > 0 ? 'Rp '.number_format($line['credit'], 0, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-row total"><span>Total Dibayar</span><span>Rp {{ number_format($totalAmount, 0, ',', '.') }}</span></div>
        <div class="summary-row" style="margin-top:8px;"><span>Tunggakan Pokok+Jasa Sebelum Bayar</span><span>Rp {{ number_format($outstandingBefore, 0, ',', '.') }}</span></div>

        <form method="POST" action="{{ route('staf.angsuran.store') }}" style="margin-top:16px;">
            @csrf
            <input type="hidden" name="loan_id" value="{{ $loan->id }}">
            <input type="hidden" name="principal_portion" value="{{ $principalPortion }}">
            <input type="hidden" name="interest_portion" value="{{ $interestPortion }}">
            <input type="hidden" name="penalty_portion" value="{{ $penaltyPortion }}">
            <input type="hidden" name="description" value="{{ $description }}">
            <input type="hidden" name="paid_at" value="{{ $paidAt }}">
            <input type="hidden" name="cash_account_id" value="{{ $cashAccountId }}">
            <button type="submit" class="btn-primary">Konfirmasi &amp; Simpan</button>
            <a href="{{ route('staf.angsuran.create') }}" class="btn-ghost">Batal</a>
        </form>
    </div>
@endsection

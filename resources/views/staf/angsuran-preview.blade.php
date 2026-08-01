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

    <h2>Rincian Alokasi — {{ $loan->loan_number }}</h2>

    <div class="panel">
        <p>Anggota: <strong>{{ $loan->member->name }}</strong></p>
        <p>Nominal Bayar: <strong>Rp {{ number_format($amount, 0, ',', '.') }}</strong></p>

        <table class="alloc-table">
            <thead><tr><th>Angsuran ke-</th><th>Pokok</th><th>Jasa</th><th>Total Dialokasikan</th></tr></thead>
            <tbody>
                @foreach ($plan['allocations'] as $allocation)
                    <tr>
                        <td>{{ $allocation['installment_number'] }}</td>
                        <td>{{ number_format($allocation['principal_share'], 0, ',', '.') }}</td>
                        <td>{{ number_format($allocation['interest_share'], 0, ',', '.') }}</td>
                        <td>{{ number_format($allocation['allocated'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-row"><span>Tunggakan Sebelum Bayar</span><span>Rp {{ number_format($plan['outstanding_before'], 0, ',', '.') }}</span></div>
        <div class="summary-row"><span>Total Pokok</span><span>Rp {{ number_format($plan['total_principal'], 0, ',', '.') }}</span></div>
        <div class="summary-row"><span>Total Jasa</span><span>Rp {{ number_format($plan['total_interest'], 0, ',', '.') }}</span></div>
        <div class="summary-row total"><span>Sisa Tunggakan Setelah Bayar</span><span>Rp {{ number_format($plan['outstanding_before'] - $plan['total_allocated'], 0, ',', '.') }}</span></div>

        <form method="POST" action="{{ route('staf.angsuran.store') }}" style="margin-top:16px;">
            @csrf
            <input type="hidden" name="loan_id" value="{{ $loan->id }}">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="description" value="{{ $description }}">
            <button type="submit" class="btn-primary">Konfirmasi &amp; Simpan</button>
            <a href="{{ route('staf.angsuran.create') }}" class="btn-ghost">Batal</a>
        </form>
    </div>
@endsection

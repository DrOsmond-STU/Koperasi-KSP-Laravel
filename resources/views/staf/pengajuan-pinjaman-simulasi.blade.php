@extends('layouts.app')

@section('title', 'Simulasi Angsuran')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 560px; }
        .schedule-table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 12px; }
        .schedule-table th, .schedule-table td { text-align: right; padding: 6px 8px; border-bottom: 1px solid var(--line); }
        .schedule-table th:first-child, .schedule-table td:first-child { text-align: left; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-ghost { padding: 10px 18px; background: transparent; color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; text-decoration: none; display: inline-block; }
    </style>

    <h2>Simulasi Angsuran — {{ $product->name }} ({{ ucfirst($product->calculation_method) }})</h2>

    <div class="panel">
        <p>Anggota: <strong>{{ $member->name }}</strong> — Plafon Rp {{ number_format($principal, 0, ',', '.') }} — Tenor {{ $tenor }} bulan — Jasa {{ $ratePercentage }}%/tahun</p>

        <table class="schedule-table">
            <thead><tr><th>#</th><th>Jatuh Tempo</th><th>Pokok</th><th>Jasa</th><th>Total</th></tr></thead>
            <tbody>
                @foreach ($schedule as $row)
                    <tr>
                        <td>{{ $row['installment_number'] }}</td>
                        <td>{{ $row['due_date']->translatedFormat('d M Y') }}</td>
                        <td>{{ number_format($row['principal_amount'], 0, ',', '.') }}</td>
                        <td>{{ number_format($row['interest_amount'], 0, ',', '.') }}</td>
                        <td>{{ number_format($row['total_amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('staf.pengajuan-pinjaman.store') }}">
            @csrf
            <input type="hidden" name="member_id" value="{{ $member->id }}">
            <input type="hidden" name="loan_product_id" value="{{ $product->id }}">
            <input type="hidden" name="principal_amount" value="{{ $principal }}">
            <input type="hidden" name="tenor_months" value="{{ $tenor }}">
            <button type="submit" class="btn-primary">Kirim Pengajuan</button>
            <a href="{{ route('staf.pengajuan-pinjaman.create') }}" class="btn-ghost">Batal</a>
        </form>
    </div>
@endsection

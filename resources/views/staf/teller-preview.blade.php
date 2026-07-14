@extends('layouts.app')

@section('title', 'Preview Jurnal')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 520px; }
        .journal-table { width: 100%; border-collapse: collapse; margin: 14px 0; }
        .journal-table th, .journal-table td { text-align: right; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .journal-table th:first-child, .journal-table td:first-child { text-align: left; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-ghost { padding: 10px 18px; background: transparent; color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; text-decoration: none; display: inline-block; }
    </style>

    <h2>Preview Jurnal — {{ ucfirst($type) }} Rp {{ number_format($amount, 0, ',', '.') }}</h2>

    <div class="panel">
        <p>Rekening: <strong>{{ $account->account_number }}</strong> — {{ $account->member->name }}</p>

        <table class="journal-table">
            <thead><tr><th>Akun</th><th>Debit</th><th>Kredit</th></tr></thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line['account_code'] }} — {{ $line['account_name'] }}</td>
                        <td>{{ $line['debit'] > 0 ? number_format($line['debit'], 0, ',', '.') : '' }}</td>
                        <td>{{ $line['credit'] > 0 ? number_format($line['credit'], 0, ',', '.') : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('staf.teller.store') }}">
            @csrf
            <input type="hidden" name="savings_account_id" value="{{ $account->id }}">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="description" value="{{ $description }}">
            <button type="submit" class="btn-primary">Konfirmasi &amp; Simpan</button>
            <a href="{{ route('staf.teller.create') }}" class="btn-ghost">Batal</a>
        </form>
    </div>
@endsection

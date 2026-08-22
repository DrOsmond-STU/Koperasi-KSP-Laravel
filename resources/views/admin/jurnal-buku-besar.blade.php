@extends('layouts.app')

@section('title', 'Buku Besar')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .data-table tr.baris-saldo-awal td { background: var(--paper); font-weight: 700; font-style: italic; }
    </style>

    <h2>Buku Besar {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>

    <form class="filter-bar" method="GET">
        <select name="chart_of_account_id" onchange="this.form.submit()" class="js-searchable">
            <option value="">— Pilih Akun —</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" {{ $selectedAccount?->id === $account->id ? 'selected' : '' }}>{{ $account->code }} — {{ $account->name }}</option>
            @endforeach
        </select>
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <input type="date" name="period_start" value="{{ $periodStart }}" onchange="this.form.submit()">
        <input type="date" name="period_end" value="{{ $periodEnd }}" onchange="this.form.submit()">
    </form>

    @if ($selectedAccount)
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo Berjalan</th></tr></thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr @class(['baris-saldo-awal' => $line['is_opening'] ?? false])>
                        <td>{{ \Illuminate\Support\Carbon::parse($line['date'])->translatedFormat('d M Y') }}</td>
                        <td>{{ $line['description'] }}</td>
                        <td>{{ ($line['is_opening'] ?? false) ? '—' : 'Rp '.number_format((float) $line['debit'], 0, ',', '.') }}</td>
                        <td>{{ ($line['is_opening'] ?? false) ? '—' : 'Rp '.number_format((float) $line['credit'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $line['running_balance'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Tidak ada mutasi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <p>Pilih akun untuk melihat Buku Besar.</p>
    @endif
@endsection

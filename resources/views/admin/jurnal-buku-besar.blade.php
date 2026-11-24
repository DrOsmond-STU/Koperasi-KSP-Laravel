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
        .btn-ekspor { padding: 8px 14px; border: 1px solid var(--pine); color: var(--pine); border-radius: 9px; font-size: 13px; font-weight: 700; text-decoration: none; background: var(--surface); }
        .btn-ekspor:hover { background: var(--pine); color: #fff; }
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
        <label style="display:flex; align-items:center; gap:7px; font-size:13px; padding:8px 12px; border:1px solid var(--line); border-radius:9px; cursor:pointer;">
            <input type="checkbox" name="tampilkan_dibatalkan" value="1" onchange="this.form.submit()" {{ ($showCancelled ?? false) ? 'checked' : '' }} style="margin:0;">
            Tampilkan jurnal yang dibatalkan
        </label>
    </form>

    @if (session('error'))
        <p style="color: var(--brick); font-size: 13px; margin: -8px 0 12px;">{{ session('error') }}</p>
    @endif

    @if ($selectedAccount)
        <div style="display:flex; gap:10px; margin:-6px 0 14px; flex-wrap:wrap;">
            <a href="{{ route('admin.jurnal-buku-besar.excel', request()->query()) }}" class="btn-ekspor">Ekspor Excel (.xlsx)</a>
            <a href="{{ route('admin.jurnal-buku-besar.pdf', request()->query()) }}" class="btn-ekspor">Ekspor PDF</a>
            <span class="hint" style="align-self:center;">Isi berkas mengikuti akun, cabang, periode & pilihan di atas.</span>
        </div>
    @endif

    @unless ($showCancelled ?? false)
        <p class="hint" style="margin-top:-8px; margin-bottom:14px;">Jurnal yang dibatalkan (entri asal + jurnal pembaliknya) tidak ditampilkan. Keduanya saling meniadakan, jadi <strong>saldo berjalan tidak terpengaruh</strong> — centang di atas untuk melihatnya.</p>
    @endunless

    @if ($selectedAccount)
        {{-- Nominal ditampilkan 2 angka desimal, sama dengan presisi yang
             disimpan di database (decimal 2) dan dihitung GeneralLedgerService
             (bcmath skala 2). Hanya format tampilan — nilainya tidak diubah. --}}
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo Berjalan</th></tr></thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr @class(['baris-saldo-awal' => $line['is_opening'] ?? false])>
                        <td>{{ \Illuminate\Support\Carbon::parse($line['date'])->translatedFormat('d M Y') }}</td>
                        <td>{{ $line['description'] }}</td>
                        <td>{{ ($line['is_opening'] ?? false) ? '—' : 'Rp '.number_format((float) $line['debit'], 2, ',', '.') }}</td>
                        <td>{{ ($line['is_opening'] ?? false) ? '—' : 'Rp '.number_format((float) $line['credit'], 2, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $line['running_balance'], 2, ',', '.') }}</td>
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

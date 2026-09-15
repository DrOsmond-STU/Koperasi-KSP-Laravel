@extends('layouts.app')

@section('title', 'Periksa Baris — '.$label)

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 9px 13px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .btn-primary { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .search-bar { display: flex; gap: 8px; margin-bottom: 14px; }
        .search-bar input { flex: 1; max-width: 380px; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .hint { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
    </style>

    @php
        $rupiah = fn ($v) => number_format((float) $v, 2, ',', '.');
    @endphp

    <h2>Periksa &amp; Ubah — {{ $label }}</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.saldo-awal.koreksi', $batch) }}" class="btn-link">&larr; Kembali ke Koreksi Data</a>
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.saldo-awal.koreksi.rows', [$batch, $subModule]) }}" class="search-bar">
        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama anggota, no. anggota, no. rekening / pinjaman, kode akun…">
        <button type="submit" class="btn-primary">Cari</button>
        @if ($search !== '')
            <a href="{{ route('admin.saldo-awal.koreksi.rows', [$batch, $subModule]) }}" class="btn-link" style="align-self:center">Reset</a>
        @endif
    </form>

    <p class="hint">Menampilkan {{ $rows->count() }} baris (maksimal 200 per tampilan — persempit dengan pencarian bila perlu).</p>

    <table class="data-table">
        <thead>
            <tr>
                @switch ($subModule)
                    @case ('savings')
                        <th>Anggota</th><th>Produk</th><th>No. Rekening</th><th class="num">Saldo Awal</th><th></th>
                        @break
                    @case ('loans')
                        <th>Anggota</th><th>Produk</th><th>No. Pinjaman Lama</th><th class="num">Plafon Awal</th><th class="num">Sisa Pokok</th><th class="num">Sisa Jasa</th><th>Kolek</th><th></th>
                        @break
                    @case ('installments')
                        <th>Pinjaman</th><th>Angsuran Ke</th><th>Jatuh Tempo</th><th class="num">Pokok</th><th class="num">Jasa</th><th>Status</th><th></th>
                        @break
                    @case ('coa')
                        <th>Akun</th><th>Posisi</th><th class="num">Saldo</th><th></th>
                        @break
                    @case ('upf')
                        <th>Jenis Retribusi</th><th class="num">Saldo Awal</th><th>Keterangan</th><th></th>
                        @break
                    @case ('stock')
                        <th>Barang</th><th class="num">Qty</th><th class="num">Harga Satuan</th><th>Keterangan</th><th></th>
                        @break
                @endswitch
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @switch ($subModule)
                        @case ('savings')
                            <td>{{ $row->member->member_number ?? '-' }} — {{ $row->member->name ?? '-' }}</td>
                            <td>{{ $row->savingsProduct->name ?? '-' }}</td>
                            <td>{{ $row->account_number ?: '-' }}</td>
                            <td class="num">{{ $rupiah($row->balance) }}</td>
                            @break
                        @case ('loans')
                            <td>{{ $row->member->member_number ?? '-' }} — {{ $row->member->name ?? '-' }}</td>
                            <td>{{ $row->loanProduct->name ?? '-' }}</td>
                            <td>{{ $row->external_loan_number ?: '-' }}</td>
                            <td class="num">{{ $rupiah($row->original_principal) }}</td>
                            <td class="num">{{ $rupiah($row->outstanding_principal) }}</td>
                            <td class="num">{{ $rupiah($row->outstanding_interest) }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($row->collectibility)) }}</td>
                            @break
                        @case ('installments')
                            <td>{{ $row->loan->external_loan_number ?? ('ID '.$row->opening_balance_loan_id) }}</td>
                            <td>{{ $row->installment_number }}</td>
                            <td>{{ $row->due_date?->format('d-m-Y') }}</td>
                            <td class="num">{{ $rupiah($row->principal_amount) }}</td>
                            <td class="num">{{ $rupiah($row->interest_amount) }}</td>
                            <td>{{ $row->status === 'sebagian' ? 'Sebagian' : 'Belum Bayar' }}</td>
                            @break
                        @case ('coa')
                            <td>{{ $row->account->code ?? '-' }} — {{ $row->account->name ?? '-' }}</td>
                            <td>{{ ucfirst($row->position) }}</td>
                            <td class="num">{{ $rupiah($row->amount) }}</td>
                            @break
                        @case ('upf')
                            <td>{{ $row->retributionType->name ?? '-' }}</td>
                            <td class="num">{{ $rupiah($row->amount) }}</td>
                            <td>{{ $row->notes ?: '-' }}</td>
                            @break
                        @case ('stock')
                            <td>{{ $row->product->code ?? '-' }} — {{ $row->product->name ?? '-' }}</td>
                            <td class="num">{{ $rupiah($row->qty) }}</td>
                            <td class="num">{{ $rupiah($row->unit_cost) }}</td>
                            <td>{{ $row->notes ?: '-' }}</td>
                            @break
                    @endswitch
                    <td>
                        @if ($batch->isDraft())
                            <a href="{{ route('admin.saldo-awal.koreksi.edit', [$batch, $subModule, $row->id]) }}" class="btn-link">Ubah</a>
                        @else
                            <span style="color: var(--muted);">Terkunci</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">Tidak ada baris yang cocok.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

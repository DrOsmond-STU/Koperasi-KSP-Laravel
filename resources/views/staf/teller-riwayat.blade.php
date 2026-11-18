@extends('layouts.app')

@section('title', 'Riwayat Transaksi Simpanan')

@section('content')
    <style>
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .field { margin-bottom: 0; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: end; row-gap: 10px; }
        .field-row .field { flex: 1; min-width: 160px; }
        .field-row .field.field-search { flex: 2 1 240px; }
        .filter-toolbar { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 18px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: var(--paper); color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn-secondary[aria-disabled="true"] { opacity: .5; cursor: default; pointer-events: none; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .data-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .pager-row { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 12px; color: var(--muted); flex-wrap: wrap; gap: 10px; }
        .pager-links { display: flex; align-items: center; gap: 8px; }
        .btn-cancel-toggle { background: transparent; border: none; color: var(--brick); font-size: 11px; font-weight: 600; cursor: pointer; padding: 0; text-decoration: underline; }
        .cancel-form { display: none; margin-top: 6px; gap: 6px; }
        .cancel-form.open { display: flex; }
        .cancel-form input[type="text"] { flex: 1; min-width: 100px; padding: 5px 8px; border: 1px solid var(--line); border-radius: 6px; font-size: 11px; }
        .cancel-form button { padding: 5px 10px; background: var(--brick); color: #fff; border: none; border-radius: 6px; font-size: 11px; cursor: pointer; }
        .table-wrap { overflow-x: auto; }
    </style>

    <h2>Riwayat Transaksi Simpanan</h2>
    <p style="color: var(--muted); margin-top: -8px;">
        Semua transaksi Setor/Tarik (bukan cuma hari ini) — cari, filter, cetak ulang bukti, atau batalkan.
        <a href="{{ route('staf.teller.create') }}">← Kembali ke Layanan Teller</a>
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    @php
        $filtersActive = $filters['q'] || $filters['type'] || $filters['status'] || $filters['date_from'] || $filters['date_to'];
    @endphp

    <div class="filter-toolbar">
        <form method="GET" action="{{ route('staf.teller.history') }}">
            <div class="field-row">
                <div class="field field-search">
                    <label>Cari</label>
                    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="No. rekening, nama anggota, atau keterangan...">
                </div>
                <div class="field">
                    <label>Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </div>
                <div class="field">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </div>
                <div class="field">
                    <label>Jenis</label>
                    <select name="type">
                        <option value="">Semua</option>
                        <option value="setor" @selected($filters['type'] === 'setor')>Setor</option>
                        <option value="tarik" @selected($filters['type'] === 'tarik')>Tarik</option>
                    </select>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Semua</option>
                        <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
                        <option value="dibatalkan" @selected($filters['status'] === 'dibatalkan')>Dibatalkan</option>
                    </select>
                </div>
                <div class="field" style="flex: 0 0 auto; min-width: 0;">
                    <button type="submit" class="btn-primary">Terapkan</button>
                </div>
                @if ($filtersActive)
                    <div class="field" style="flex: 0 0 auto; min-width: 0;">
                        <a href="{{ route('staf.teller.history') }}" class="btn-secondary">Reset</a>
                    </div>
                @endif
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Rekening</th>
                    <th>Anggota</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Saldo Sesudah</th>
                    <th>Status / Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $trx)
                    <tr>
                        <td>{{ $trx->transactionOn()->format('d/m/Y') }}</td>
                        <td>{{ $trx->savingsAccount->account_number }}</td>
                        <td>{{ $trx->savingsAccount->member->name }}</td>
                        <td>{{ ucfirst($trx->type) }}</td>
                        <td class="num">Rp {{ number_format($trx->amount, 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format($trx->balance_after, 0, ',', '.') }}</td>
                        <td>
                            <a href="{{ route('staf.teller.print-receipt', $trx) }}" target="_blank" style="font-size:11px;">Cetak Bukti</a>
                            @if ($trx->isCancelled())
                                <p style="font-size:11px; color:var(--muted); margin: 2px 0 0;">Dibatalkan: {{ $trx->cancellation_reason }}</p>
                            @elseif ($trx->canBeCancelledBy(auth()->user()))
                                <br>
                                <a href="{{ route('staf.teller.edit', $trx) }}" style="font-size:11px;">Edit</a>
                                &nbsp;·&nbsp;
                                <button type="button" class="btn-cancel-toggle" data-toggle-cancel="{{ $trx->id }}">Batalkan</button>
                                <form method="POST" action="{{ route('staf.teller.cancel', $trx) }}" class="cancel-form" id="cancel-form-{{ $trx->id }}">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Alasan pembatalan" required>
                                    <button type="submit">OK</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">{{ $filtersActive ? 'Tidak ada transaksi yang cocok dengan filter ini.' : 'Belum ada transaksi simpanan.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($transactions->total() > 0)
        <div class="pager-row">
            <span>Menampilkan {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} dari {{ $transactions->total() }} transaksi</span>
            @if ($transactions->hasPages())
                <div class="pager-links">
                    <a href="{{ $transactions->previousPageUrl() ?? '#' }}" class="btn-secondary" @if ($transactions->onFirstPage()) aria-disabled="true" @endif>‹ Sebelumnya</a>
                    <span>Halaman {{ $transactions->currentPage() }} / {{ $transactions->lastPage() }}</span>
                    <a href="{{ $transactions->nextPageUrl() ?? '#' }}" class="btn-secondary" @if (! $transactions->hasMorePages()) aria-disabled="true" @endif>Berikutnya ›</a>
                </div>
            @endif
        </div>
    @endif

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.classList.toggle('open');
            });
        });
    </script>
@endsection

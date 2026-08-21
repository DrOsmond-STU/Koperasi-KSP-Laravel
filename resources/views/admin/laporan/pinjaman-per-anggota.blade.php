@extends('layouts.app')

@section('title', 'Laporan Pinjaman per Anggota')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .data-table td.num, .data-table th.num { text-align: right; }
        .btn-primary { display:inline-block; padding: 8px 14px; background: var(--pine); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 12px; }
        .summary-card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 14px 18px; margin-bottom: 14px; }
        .summary-card h3 { margin: 0 0 6px; font-size: 14px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 12px; }
        .summary-grid .label { color: var(--muted); }
        .summary-grid .value { font-weight: 700; font-size: 14px; }
        details { margin-top: 6px; }
        details summary { cursor: pointer; color: var(--pine); font-size: 12px; font-weight: 600; padding: 4px 0; }
    </style>

    <h2>Laporan Pinjaman per Anggota</h2>

    <form class="filter-bar" method="GET">
        <select name="member_id" onchange="this.form.submit()" class="js-searchable" style="min-width: 260px;">
            <option value="">— Pilih Anggota —</option>
            @foreach ($members as $m)
                <option value="{{ $m->id }}" @selected($selectedMember?->id === $m->id)>
                    {{ $m->member_number ? '['.$m->member_number.'] ' : '' }}{{ $m->name }}
                </option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()">
            <option value="all" @selected($status === 'all')>Semua Status</option>
            <option value="aktif" @selected($status === 'aktif')>Hanya Aktif</option>
            <option value="lunas" @selected($status === 'lunas')>Hanya Lunas</option>
        </select>
        <input type="date" name="period_start" value="{{ $periodStart }}" placeholder="Cair dari" onchange="this.form.submit()">
        <input type="date" name="period_end" value="{{ $periodEnd }}" placeholder="Cair sampai" onchange="this.form.submit()">
    </form>

    @if ($summary)
        <div class="summary-card">
            <h3>{{ $summary['member']->name }} <span style="color:var(--muted); font-weight:400;">({{ $summary['member']->member_number ?? 'tanpa nomor' }})</span></h3>
            <div class="summary-grid">
                <div>
                    <div class="label">Total Pokok Pinjaman</div>
                    <div class="value">Rp {{ number_format($summary['totals']['principal'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="label">Sudah Dibayar (Pokok+Jasa)</div>
                    <div class="value">Rp {{ number_format($summary['totals']['paid_total'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="label">Outstanding Pokok</div>
                    <div class="value">Rp {{ number_format($summary['totals']['outstanding_principal'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="label">Outstanding Total</div>
                    <div class="value">Rp {{ number_format($summary['totals']['outstanding_total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <p style="margin-bottom: 14px;">
            <a class="btn-primary" target="_blank" href="{{ route('admin.laporan.pinjaman-per-anggota.print', request()->only(['member_id', 'status', 'period_start', 'period_end'])) }}">
                Cetak PDF (dengan lampiran rincian pembayaran)
            </a>
        </p>

        @if ($summary['loans']->isEmpty())
            <p style="color: var(--muted);">Tidak ada pinjaman yang memenuhi filter.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. Pinjaman</th>
                        <th>Produk</th>
                        <th>Tgl Cair</th>
                        <th>Tenor</th>
                        <th class="num">Pokok</th>
                        <th class="num">Terbayar</th>
                        <th class="num">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['loans'] as $row)
                        <tr>
                            <td>{{ $row['loan']->loan_number }}</td>
                            <td>{{ $row['loan']->loanProduct->name }}</td>
                            <td>{{ $row['loan']->disbursed_at?->translatedFormat('d M Y') ?? '-' }}</td>
                            <td>{{ $row['loan']->tenor_days }} hari</td>
                            <td class="num">Rp {{ number_format($row['principal'], 0, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($row['paid_total'], 0, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($row['outstanding_total'], 0, ',', '.') }}</td>
                            <td>{{ $row['outstanding_total'] <= 0.5 ? 'Lunas' : ucfirst($row['loan']->status) }}</td>
                        </tr>
                        <tr>
                            <td colspan="8" style="padding: 0 14px 10px;">
                                <details>
                                    <summary>Rincian {{ $row['repayments']->count() }} pembayaran</summary>
                                    <table style="width: 100%; margin-top: 6px; font-size: 12px; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: var(--paper);">
                                                <th style="text-align:left; padding: 6px 8px;">Tanggal</th>
                                                <th style="text-align:left; padding: 6px 8px;">Keterangan</th>
                                                <th style="text-align:right; padding: 6px 8px;">Pokok</th>
                                                <th style="text-align:right; padding: 6px 8px;">Jasa</th>
                                                <th style="text-align:right; padding: 6px 8px;">Jumlah</th>
                                                <th style="text-align:right; padding: 6px 8px;">Sisa Setelah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($row['repayments'] as $rp)
                                                <tr>
                                                    <td style="padding: 4px 8px;">{{ $rp->created_at->translatedFormat('d M Y') }}</td>
                                                    <td style="padding: 4px 8px;">{{ $rp->description ?? '-' }}</td>
                                                    <td style="text-align:right; padding: 4px 8px;">Rp {{ number_format((float) $rp->principal_portion, 0, ',', '.') }}</td>
                                                    <td style="text-align:right; padding: 4px 8px;">Rp {{ number_format((float) $rp->interest_portion, 0, ',', '.') }}</td>
                                                    <td style="text-align:right; padding: 4px 8px;">Rp {{ number_format((float) $rp->amount, 0, ',', '.') }}</td>
                                                    <td style="text-align:right; padding: 4px 8px;">Rp {{ number_format((float) $rp->balance_after, 0, ',', '.') }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" style="text-align:center; padding: 6px; color: var(--muted);">Belum ada pembayaran.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        <p style="color: var(--muted);">Pilih anggota untuk melihat rangkuman pinjamannya.</p>
    @endif
@endsection

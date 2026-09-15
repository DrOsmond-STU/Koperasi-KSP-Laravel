@extends('layouts.app')

@section('title', 'Laporan Jurnal Transaksi')

@section('content')
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; align-items: flex-end; }
        .filter-bar label { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 600; color: var(--muted); }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .filter-bar .btn-primary { padding: 9px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .filter-bar .btn-link { padding: 9px 4px; font-size: 12px; }
        .hint { font-size: 11.5px; color: var(--muted); }

        .ringkas { display: flex; gap: 22px; flex-wrap: wrap; padding: 12px 16px; margin: 14px 0 18px; border: 1px solid var(--line); border-radius: 11px; background: var(--surface); }
        .ringkas div { font-size: 12px; color: var(--muted); }
        .ringkas strong { display: block; font-size: 15px; color: var(--ink); font-variant-numeric: tabular-nums; margin-top: 2px; }
        .ringkas.timpang { border-color: var(--brick); background: var(--brick-soft); }
        .ringkas.timpang strong { color: var(--brick); }

        .jurnal-tabel { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .jurnal-tabel th { text-align: left; padding: 9px 14px; background: var(--paper); font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 700; }
        .jurnal-tabel td { padding: 7px 14px; font-size: 13px; border-bottom: 1px solid var(--line); }
        .jurnal-tabel .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

        .baris-kepala td { background: var(--paper); border-top: 2px solid var(--line); padding-top: 11px; padding-bottom: 9px; }
        .kepala-judul { font-weight: 700; }
        .kepala-meta { font-size: 11.5px; color: var(--muted); margin-top: 2px; }
        .baris-total td { font-weight: 700; border-bottom: 2px solid var(--line); }
        .kode-akun { color: var(--muted); font-variant-numeric: tabular-nums; }
        .indent { padding-left: 30px !important; }

        .tanda { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; vertical-align: middle; }
        .tanda-sumber { background: var(--leaf); color: var(--pine-bright); }
        .tanda-batal { background: var(--brick-soft); color: var(--brick); }
        .tabel-gulir { overflow-x: auto; }
    </style>

    <h2>Laporan Jurnal Transaksi</h2>
    <p class="hint" style="margin-top:-8px; margin-bottom:16px; max-width:760px;">
        Setiap transaksi yang masuk ke pembukuan — pencairan pinjaman, angsuran, simpanan, retribusi,
        kas toko, maupun jurnal manual — ditampilkan lengkap dengan akun yang didebet dan dikredit.
        Berbeda dari Buku Besar yang menampilkan mutasi satu akun saja, di sini satu transaksi terlihat utuh.
    </p>

    <form class="filter-bar" method="GET">
        <label>
            Dari tanggal
            <input type="date" name="date_from" value="{{ $filter['date_from'] }}">
        </label>
        <label>
            Sampai tanggal
            <input type="date" name="date_to" value="{{ $filter['date_to'] }}">
        </label>
        <label>
            Cabang
            <select name="branch_id">
                <option value="">Semua cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($filter['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Jenis transaksi
            <select name="source_type">
                <option value="">Semua jenis</option>
                @foreach ($sumberOptions as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($filter['source_type'] === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Akun
            <select name="chart_of_account_id" class="js-searchable">
                <option value="">Semua akun</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($filter['chart_of_account_id'] === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Cari keterangan
            <input type="search" name="q" value="{{ $filter['q'] }}" placeholder="mis. nomor pinjaman">
        </label>
        <button type="submit" class="btn-primary">Tampilkan</button>
        <a class="btn-link" href="{{ route('admin.jurnal-transaksi.index') }}">Reset</a>
        <a class="btn-link" href="{{ route('admin.jurnal-transaksi.print', request()->query()) }}" target="_blank">Cetak PDF</a>
    </form>

    <div class="ringkas {{ $totals['balanced'] ? '' : 'timpang' }}">
        <div>Jumlah transaksi<strong>{{ number_format($totals['entries'], 0, ',', '.') }}</strong></div>
        <div>Jumlah baris jurnal<strong>{{ number_format($totals['lines'], 0, ',', '.') }}</strong></div>
        <div>Total debet<strong>Rp {{ number_format((float) $totals['debit'], 0, ',', '.') }}</strong></div>
        <div>Total kredit<strong>Rp {{ number_format((float) $totals['credit'], 0, ',', '.') }}</strong></div>
        <div>Keseimbangan<strong>{{ $totals['balanced'] ? 'Seimbang' : 'TIDAK SEIMBANG' }}</strong></div>
    </div>

    @unless ($totals['balanced'])
        <p class="hint" style="color:var(--brick); margin-top:-10px; margin-bottom:16px;">
            Total debet dan kredit pada rentang ini tidak sama. Setiap jurnal diposting seimbang, jadi
            selisih di sini berarti rentang tanggalnya memotong sebuah transaksi — bukan pembukuan yang rusak.
        </p>
    @endunless

    <div class="tabel-gulir">
        <table class="jurnal-tabel">
            <thead>
                <tr>
                    <th style="width:110px;">Kode Akun</th>
                    <th>Nama Akun</th>
                    <th class="num" style="width:150px;">Debet</th>
                    <th class="num" style="width:150px;">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php
                        $dibatalkan = $entry->reversals->isNotEmpty();
                        $adalahBalik = $entry->reversal_of_entry_id !== null;
                    @endphp
                    <tr class="baris-kepala">
                        <td colspan="4">
                            <div class="kepala-judul">
                                {{ $entry->entry_date->translatedFormat('d M Y') }} &nbsp;·&nbsp; {{ $entry->description }}
                                <span class="tanda tanda-sumber">{{ $reportService->labelSumber($entry->source_type) }}</span>
                                @if ($adalahBalik)
                                    <span class="tanda tanda-batal">Jurnal Balik</span>
                                @endif
                                @if ($dibatalkan)
                                    <span class="tanda tanda-batal">Dibatalkan</span>
                                @endif
                            </div>
                            <div class="kepala-meta">
                                No. Jurnal #{{ $entry->id }} · {{ $entry->branch?->name ?? 'Tanpa cabang' }}
                                · diinput {{ $entry->createdBy?->name ?? 'sistem' }}
                                @if ($entry->created_at && $entry->created_at->toDateString() !== $entry->entry_date->toDateString())
                                    pada {{ $entry->created_at->translatedFormat('d M Y') }} (dicatat susulan)
                                @endif
                            </div>
                        </td>
                    </tr>
                    @foreach ($entry->lines as $line)
                        <tr>
                            <td class="kode-akun indent">{{ $line->account?->code ?? '—' }}</td>
                            <td>{{ $line->account?->name ?? 'Akun terhapus' }}</td>
                            <td class="num">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 0, ',', '.') : '' }}</td>
                            <td class="num">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 0, ',', '.') : '' }}</td>
                        </tr>
                    @endforeach
                    <tr class="baris-total">
                        <td colspan="2" class="num">Total transaksi ini</td>
                        <td class="num">{{ number_format((float) $entry->lines->sum('debit'), 0, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $entry->lines->sum('credit'), 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Tidak ada transaksi pada rentang dan filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">
        {{ $entries->links() }}
    </div>
@endsection

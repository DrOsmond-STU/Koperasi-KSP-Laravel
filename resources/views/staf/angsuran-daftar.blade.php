@extends('layouts.app')

@section('title', 'Daftar Angsuran Anggota')

@section('content')
    <style>
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: end; row-gap: 10px; }
        .field-row .field { flex: 1; min-width: 150px; }
        .field-row .field.field-wide { flex: 2 1 240px; }
        .field-row .field.field-tight { flex: 0 0 auto; min-width: 0; }
        .filter-toolbar { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 16px; }
        .btn-primary { display: inline-block; padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: var(--paper); color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; }
        .rekap { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .rekap div { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
        .rekap span { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 4px; }
        .rekap strong { font-size: 15px; font-variant-numeric: tabular-nums; }
        .rekap small { display: block; font-size: 11px; color: var(--muted); margin-top: 2px; }
        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 9px 12px; border-bottom: 1px solid var(--line); font-size: 13px; vertical-align: top; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); white-space: nowrap; }
        .data-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .data-table tr.batal td { color: var(--muted); }
        .data-table tr.batal td.num { text-decoration: line-through; }
        .badge-batal { display: inline-block; padding: 1px 8px; border-radius: 999px; background: var(--brick-soft); color: var(--brick); font-size: 11px; font-weight: 700; }
        .badge-aktif { display: inline-block; padding: 1px 8px; border-radius: 999px; background: var(--leaf); color: var(--pine); font-size: 11px; font-weight: 700; }
        .sub { display: block; font-size: 11px; color: var(--muted); }
        .btn-cancel-toggle { background: transparent; border: none; color: var(--brick); font-size: 11px; font-weight: 600; cursor: pointer; padding: 0; text-decoration: underline; }
        .cancel-form { display: none; margin-top: 6px; gap: 6px; }
        .cancel-form.open { display: flex; }
        .cancel-form input[type="text"] { flex: 1; min-width: 110px; padding: 5px 8px; border: 1px solid var(--line); border-radius: 6px; font-size: 11px; }
        .cancel-form button { padding: 5px 10px; background: var(--brick); color: #fff; border: none; border-radius: 6px; font-size: 11px; cursor: pointer; }
        .penavigasi { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 12px; font-size: 13px; color: var(--muted); }
        .penavigasi-halaman { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
        .penavigasi-halaman a, .penavigasi-halaman span.nomor { min-width: 34px; box-sizing: border-box; text-align: center; padding: 6px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); color: var(--pine); text-decoration: none; font-weight: 600; }
        .penavigasi-halaman span.aktif { background: var(--pine); border-color: var(--pine); color: #fff; }
        .penavigasi-halaman span.nonaktif { opacity: .45; }
        .penavigasi form { display: flex; align-items: center; gap: 6px; }
        .penavigasi select { padding: 6px 8px; border: 1px solid var(--line); border-radius: 8px; font-size: 13px; background: var(--surface); color: inherit; }
    </style>

    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 2, ',', '.');
        $filtersActive = $filters['q'] || $filters['member_id'] || $filters['date_from'] || $filters['date_to'] || $filters['status'];
        // Cetak memakai filter yang sama persis dengan layar (tanpa halaman).
        $cetakQuery = array_filter([
            'q' => $filters['q'],
            'member_id' => $filters['member_id'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'status' => $filters['status'],
        ]);
    @endphp

    <h2>Daftar Angsuran Anggota</h2>
    <p style="color: var(--muted); margin-top: -8px;">
        Semua pembayaran angsuran yang sudah dicatat — cari, saring per periode atau per anggota, lalu cetak sesuai saringan.
        @can('pinjaman.create')
            <a href="{{ route('staf.angsuran.create') }}">← Catat Angsuran</a>
        @endcan
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="filter-toolbar">
        <form method="GET" action="{{ route('staf.angsuran.daftar') }}">
            <input type="hidden" name="per_page" value="{{ $perHalaman }}">
            <div class="field-row">
                <div class="field field-wide">
                    <label for="f-q">Cari</label>
                    <input id="f-q" type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nama/no. anggota, no. pinjaman, atau keterangan...">
                </div>
                <div class="field">
                    <label for="f-dari">Dari Tanggal</label>
                    <input id="f-dari" type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </div>
                <div class="field">
                    <label for="f-sampai">Sampai Tanggal</label>
                    <input id="f-sampai" type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </div>
                <div class="field field-wide">
                    <label for="f-anggota">Anggota</label>
                    <select id="f-anggota" name="member_id" class="js-searchable">
                        <option value="">Semua Anggota</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected($filters['member_id'] === $member->id)>{{ $member->member_number }} — {{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="f-status">Status</label>
                    <select id="f-status" name="status">
                        <option value="">Semua</option>
                        <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
                        <option value="dibatalkan" @selected($filters['status'] === 'dibatalkan')>Dibatalkan</option>
                    </select>
                </div>
                <div class="field field-tight">
                    <button type="submit" class="btn-primary">Terapkan</button>
                </div>
                @if ($filtersActive)
                    <div class="field field-tight">
                        <a href="{{ route('staf.angsuran.daftar') }}" class="btn-secondary">Reset</a>
                    </div>
                @endif
                <div class="field field-tight">
                    <a href="{{ route('staf.angsuran.daftar.cetak', $cetakQuery) }}" class="btn-secondary" target="_blank">🖨 Cetak Laporan (PDF)</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Rekap seluruh hasil filter (semua halaman), angsuran batal tidak dijumlah. --}}
    <div class="rekap">
        <div>
            <span>Jumlah Angsuran</span>
            <strong>{{ number_format($totals['jumlah'], 0, ',', '.') }}</strong>
            @if ($totals['jumlah_batal'] > 0)
                <small>+ {{ number_format($totals['jumlah_batal'], 0, ',', '.') }} dibatalkan (tidak dijumlah)</small>
            @endif
        </div>
        <div><span>Total Pokok</span><strong>{{ $rp($totals['pokok']) }}</strong></div>
        <div><span>Total Jasa</span><strong>{{ $rp($totals['jasa']) }}</strong></div>
        <div><span>Total Denda</span><strong>{{ $rp($totals['denda']) }}</strong></div>
        <div><span>Total Bayar</span><strong>{{ $rp($totals['total']) }}</strong></div>
    </div>

    <div class="table-wrap">
        {{-- data-no-paginate: sudah dipaginasi di server. --}}
        <table class="data-table" data-no-paginate>
            <thead>
                <tr>
                    <th>Tanggal Bayar</th>
                    <th>No. Pinjaman</th>
                    <th>Anggota</th>
                    <th class="num">Pokok</th>
                    <th class="num">Jasa</th>
                    <th class="num">Denda</th>
                    <th class="num">Total</th>
                    <th>Keterangan</th>
                    <th>Status / Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($repayments as $repayment)
                    @php $member = $repayment->loan?->member; @endphp
                    <tr @class(['batal' => $repayment->isCancelled()])>
                        <td>
                            {{ $repayment->paidOn()->format('d/m/Y') }}
                            <span class="sub">Input {{ $repayment->created_at?->format('d/m/Y H:i') }}</span>
                        </td>
                        <td>{{ $repayment->loan?->loan_number ?? '—' }}</td>
                        <td>
                            {{ $member?->name ?? '—' }}
                            <span class="sub">{{ $member?->member_number }}</span>
                        </td>
                        <td class="num">{{ $rp($repayment->principal_portion) }}</td>
                        <td class="num">{{ $rp($repayment->interest_portion) }}</td>
                        <td class="num">{{ $rp($repayment->penalty_portion) }}</td>
                        <td class="num"><strong>{{ $rp($repayment->amount) }}</strong></td>
                        <td>
                            {{ $repayment->description ?: '—' }}
                            @if ($repayment->createdBy)
                                <span class="sub">oleh {{ $repayment->createdBy->name }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($repayment->isCancelled())
                                <span class="badge-batal">BATAL</span>
                                <span class="sub">{{ $repayment->cancellation_reason }}</span>
                            @else
                                <span class="badge-aktif">Aktif</span>
                            @endif
                            @can('pinjaman.print')
                                <br><a href="{{ route('admin.print.loan-repayment.show', $repayment) }}" target="_blank" style="font-size:11px;">Cetak Bukti</a>
                            @endcan
                            @if (! $repayment->isCancelled() && $repayment->canBeCancelledBy(auth()->user()))
                                &nbsp;·&nbsp;
                                <button type="button" class="btn-cancel-toggle" data-toggle-cancel="{{ $repayment->id }}">Batalkan</button>
                                <form method="POST" action="{{ route('staf.angsuran.cancel', $repayment) }}" class="cancel-form" id="cancel-form-{{ $repayment->id }}">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Alasan pembatalan" required>
                                    <button type="submit">Konfirmasi</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">{{ $filtersActive ? 'Tidak ada angsuran yang cocok dengan filter ini.' : 'Belum ada angsuran yang dicatat.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($repayments->total() > 0)
        @php
            $halamanIni = $repayments->currentPage();
            $halamanAkhir = $repayments->lastPage();
            $dari = max(1, $halamanIni - 2);
            $sampai = min($halamanAkhir, $halamanIni + 2);
        @endphp
        <div class="penavigasi">
            <span>
                Menampilkan {{ number_format($repayments->firstItem(), 0, ',', '.') }}–{{ number_format($repayments->lastItem(), 0, ',', '.') }}
                dari {{ number_format($repayments->total(), 0, ',', '.') }} angsuran
                · Halaman {{ $halamanIni }} dari {{ $halamanAkhir }}
            </span>

            @if ($repayments->hasPages())
                <nav class="penavigasi-halaman" aria-label="Halaman daftar angsuran">
                    @if ($repayments->onFirstPage())
                        <span class="nomor nonaktif">‹</span>
                    @else
                        <a href="{{ $repayments->previousPageUrl() }}" title="Sebelumnya">‹</a>
                    @endif
                    @if ($dari > 1)
                        <a href="{{ $repayments->url(1) }}">1</a>
                        @if ($dari > 2)<span>…</span>@endif
                    @endif
                    @for ($p = $dari; $p <= $sampai; $p++)
                        @if ($p === $halamanIni)
                            <span class="nomor aktif" aria-current="page">{{ $p }}</span>
                        @else
                            <a href="{{ $repayments->url($p) }}">{{ $p }}</a>
                        @endif
                    @endfor
                    @if ($sampai < $halamanAkhir)
                        @if ($sampai < $halamanAkhir - 1)<span>…</span>@endif
                        <a href="{{ $repayments->url($halamanAkhir) }}">{{ $halamanAkhir }}</a>
                    @endif
                    @if ($repayments->hasMorePages())
                        <a href="{{ $repayments->nextPageUrl() }}" title="Berikutnya">›</a>
                    @else
                        <span class="nomor nonaktif">›</span>
                    @endif
                </nav>
            @endif

            <form method="GET" action="{{ route('staf.angsuran.daftar') }}">
                @foreach ($cetakQuery as $nama => $nilai)
                    <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                @endforeach
                <label for="per-page-angsuran">Tampilkan</label>
                <select id="per-page-angsuran" name="per_page" onchange="this.form.submit()">
                    @foreach ($pilihanPerHalaman as $n)
                        <option value="{{ $n }}" @selected($n === $perHalaman)>{{ $n }}</option>
                    @endforeach
                </select>
                <span>per halaman</span>
                <noscript><button type="submit" class="btn-secondary">OK</button></noscript>
            </form>
        </div>
    @endif

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('cancel-form-' + btn.dataset.toggleCancel).classList.toggle('open');
            });
        });
    </script>
@endsection

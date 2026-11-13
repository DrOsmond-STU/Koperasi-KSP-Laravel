@extends('layouts.app')

@section('title', 'Ubah Saldo Awal Neraca')

@section('content')
    <style>
        .banner { border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; }
        .banner-ok { background: var(--leaf); color: var(--pine-bright); border: 1px solid var(--pine); }
        .banner-bad { background: var(--brick-soft); color: var(--brick); border: 1px solid var(--brick); }
        .banner-info { background: var(--surface); border: 1px solid var(--line); color: var(--muted); }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .data-table td.num, .data-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .data-table tr.changed td { background: var(--leaf); }
        .data-table input[type=number] { width: 170px; padding: 5px 8px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; text-align: right; box-sizing: border-box; }
        .group-row td { font-weight: 700; background: var(--paper); color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .badge-control { display: inline-block; font-size: 10px; padding: 1px 6px; border-radius: 10px; background: var(--brick-soft); color: var(--brick); font-weight: 700; margin-left: 4px; }
        .field-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .field-row label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 3px; }
        .field-row input, .field-row select, .field-row textarea { width: 100%; padding: 7px 9px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; box-sizing: border-box; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin: 12px 0; }
        .summary div { background: var(--paper); border-radius: 10px; padding: 10px 12px; font-size: 12px; color: var(--muted); }
        .summary strong { display: block; font-size: 15px; color: var(--ink, inherit); margin-top: 3px; font-variant-numeric: tabular-nums; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .btn-secondary { padding: 7px 12px; background: transparent; color: var(--pine); border: 1px solid var(--pine); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-list { background: var(--brick-soft); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: var(--brick); }
        .muted { color: var(--muted); font-size: 12px; }
        .table-scroll { overflow-x: auto; }
        .filter { padding: 6px 9px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; width: 260px; max-width: 100%; }
    </style>

    @php
        $isDraft = $batch->isDraft();
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    @endphp

    <p><a class="btn-secondary" href="{{ route('admin.saldo-awal.show', $batch) }}">← Kembali ke batch</a></p>

    <h2>Ubah Saldo Awal Neraca — {{ $batch->branch->name }} (Cut-off {{ $batch->cutoff_date->translatedFormat('d M Y') }})</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <div class="banner banner-bad">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="error-list">
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="banner banner-info">
        @if ($isDraft)
            Batch masih <strong>draft</strong>: saldo baru yang Anda isi akan <strong>langsung mengganti</strong> baris COA akun terkait di batch ini (belum ada jurnal).
        @else
            Batch sudah <strong>dikunci</strong>: baris saldo awal asli tidak diubah. Selisih antara saldo lama dan saldo baru akan diposting sebagai
            <strong>jurnal koreksi saldo awal</strong> (default bertanggal cut-off) dan tercatat di riwayat koreksi di bawah. Diperlukan konfirmasi kata sandi.
        @endif
        Isi kolom <em>Saldo Baru</em> dalam arah <strong>saldo normal</strong> akun (Aset = debit, Kewajiban/Ekuitas = kredit). Nilai negatif berarti saldo berlawanan arah.
    </div>

    <div class="banner {{ bccomp($totals['debit'], $totals['credit'], 2) === 0 ? 'banner-ok' : 'banner-bad' }}">
        Saldo awal saat ini — Debit Rp {{ $fmt($totals['debit']) }} · Kredit Rp {{ $fmt($totals['credit']) }}
        @if (bccomp($totals['debit'], $totals['credit'], 2) !== 0)
            · <strong>Selisih Rp {{ $fmt(abs((float) bcsub($totals['debit'], $totals['credit'], 2))) }}</strong>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.saldo-awal.koreksi.store', $batch) }}" id="adjust-form">
        @csrf

        <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px">
                <h3 style="margin:0">Saldo per Akun Neraca</h3>
                <input type="search" class="filter" id="account-filter" placeholder="Cari kode / nama akun…">
            </div>
            <div class="table-scroll">
                <table class="data-table" id="balance-table">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Saldo Normal</th>
                            <th class="num">Saldo Awal Saat Ini</th>
                            <th class="num">Saldo Baru</th>
                            <th class="num">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $currentType = null; @endphp
                    @foreach ($rows as $row)
                        @if ($row['account']->type !== $currentType)
                            @php $currentType = $row['account']->type; @endphp
                            <tr class="group-row"><td colspan="5">{{ $currentType }}</td></tr>
                        @endif
                        <tr class="balance-row"
                            data-search="{{ strtolower($row['account']->code.' '.$row['account']->name) }}"
                            data-normal="{{ $row['account']->normal_balance }}"
                            data-current="{{ $row['balance'] }}"
                            data-account-id="{{ $row['account']->id }}">
                            <td>
                                {{ $row['account']->code }} — {{ $row['account']->name }}
                                @if ($row['is_control'])
                                    <span class="badge-control" title="Akun ini dipakai produk Simpanan/Pinjaman/Barang. Mengubah saldonya membuat buku besar tidak sama lagi dengan rincian per anggota/barang.">Kontrol sub-ledger</span>
                                @endif
                            </td>
                            <td>{{ ucfirst(strtolower($row['account']->normal_balance)) }}</td>
                            <td class="num">{{ $fmt($row['balance']) }}</td>
                            <td class="num">
                                <input type="number" step="0.01"
                                       name="balances[{{ $row['account']->id }}]"
                                       value="{{ old('balances.'.$row['account']->id, $row['balance']) }}"
                                       aria-label="Saldo baru {{ $row['account']->code }}">
                            </td>
                            <td class="num delta">—</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted">Akun 3150 (SHU Tahun Berjalan) tidak ditampilkan karena saldonya dihitung otomatis dari laba/rugi berjalan.</p>
        </div>

        <div class="panel">
            <h3 style="margin-top:0">Ringkasan Perubahan</h3>
            <div class="summary">
                <div>Akun berubah<strong id="sum-count">0</strong></div>
                <div>Total perubahan Debit<strong id="sum-debit">0,00</strong></div>
                <div>Total perubahan Kredit<strong id="sum-credit">0,00</strong></div>
                <div>Selisih (ke akun penyeimbang)<strong id="sum-diff">0,00</strong></div>
            </div>

            <div class="field-row">
                <div>
                    <label>Akun Penyeimbang Selisih</label>
                    <select name="balancing_account_id" id="balancing-account" class="js-searchable">
                        <option value="">- tanpa akun penyeimbang -</option>
                        @foreach ($rows as $row)
                            <option value="{{ $row['account']->id }}" @selected((string) old('balancing_account_id', $defaultBalancingAccountId) === (string) $row['account']->id)>
                                {{ $row['account']->code }} — {{ $row['account']->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @unless ($isDraft)
                    <div>
                        <label>Tanggal Jurnal Koreksi</label>
                        <input type="date" name="entry_date" min="{{ $batch->cutoff_date->toDateString() }}" value="{{ old('entry_date', $batch->cutoff_date->toDateString()) }}">
                    </div>
                @endunless
            </div>
            <p class="muted" id="balancing-hint">Bila total perubahan Debit ≠ Kredit, selisihnya otomatis dibukukan ke akun penyeimbang ini (umumnya 3160 SHU Ditahan / Tahun Lalu).
                @if ($isDraft) Pada batch draft, kosongkan bila ingin menyimpan dulu tanpa diseimbangkan. @else Pada batch terkunci, akun penyeimbang wajib bila ada selisih. @endif
            </p>

            <div class="field-row">
                <div style="grid-column: 1 / -1">
                    <label>Alasan / Keterangan Perubahan @unless ($isDraft)<span style="color:var(--brick)">*</span>@endunless</label>
                    <textarea name="reason" rows="2" maxlength="500" @unless ($isDraft) required @endunless placeholder="Mis. Koreksi selisih kas & piutang hasil rekonsiliasi dengan neraca sistem lama">{{ old('reason') }}</textarea>
                </div>
                @unless ($isDraft)
                    <div>
                        <label>Konfirmasi Kata Sandi <span style="color:var(--brick)">*</span></label>
                        <input type="password" name="password" required autocomplete="current-password">
                    </div>
                @endunless
            </div>

            <button type="submit" class="btn-primary"
                    onclick="return confirm('{{ $isDraft ? 'Simpan saldo baru ke baris COA batch ini?' : 'Posting jurnal koreksi saldo awal? Jurnal tidak bisa dihapus, hanya bisa dikoreksi lagi.' }}');">
                {{ $isDraft ? 'Simpan Perubahan Saldo Awal' : 'Posting Koreksi Saldo Awal' }}
            </button>
        </div>
    </form>

    @unless ($isDraft)
        <div class="panel">
            <h3 style="margin-top:0">Riwayat Koreksi Saldo Awal ({{ $adjustments->count() }})</h3>
            @forelse ($adjustments as $adjustment)
                <div style="border-top:1px solid var(--line); padding-top:10px; margin-top:10px">
                    <strong>#{{ $adjustment->id }}</strong> · Tgl jurnal {{ $adjustment->entry_date->translatedFormat('d M Y') }}
                    · oleh {{ $adjustment->creator?->name ?? '-' }} pada {{ $adjustment->created_at->translatedFormat('d M Y H:i') }}
                    @if ($adjustment->journal_entry_id) · Jurnal #{{ $adjustment->journal_entry_id }} @endif
                    <div class="muted">{{ $adjustment->reason }}</div>
                    <div class="table-scroll">
                        <table class="data-table">
                            <thead><tr><th>Akun</th><th class="num">Saldo Lama</th><th class="num">Saldo Baru</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
                            <tbody>
                            @foreach ($adjustment->lines as $line)
                                <tr>
                                    <td>{{ $line->account?->code }} — {{ $line->account?->name }} @if ($line->is_balancing)<em class="muted">(penyeimbang)</em>@endif</td>
                                    <td class="num">{{ $fmt($line->balance_before) }}</td>
                                    <td class="num">{{ $fmt($line->balance_after) }}</td>
                                    <td class="num">{{ $fmt($line->debit) }}</td>
                                    <td class="num">{{ $fmt($line->credit) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="muted">Belum ada koreksi.</p>
            @endforelse
        </div>
    @endunless

    <script>
        (function () {
            const rows = Array.from(document.querySelectorAll('#balance-table .balance-row'));
            const fmt = (cents) => (cents / 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const toCents = (v) => Math.round((parseFloat(v) || 0) * 100);

            function recalc() {
                let count = 0, debit = 0, credit = 0;
                rows.forEach((row) => {
                    const input = row.querySelector('input');
                    const current = toCents(row.dataset.current);
                    const next = input.value === '' ? current : toCents(input.value);
                    const deltaNormal = next - current;
                    // Arah debit-positif: akun saldo normal kredit dibalik tandanya.
                    const deltaRaw = row.dataset.normal === 'DEBIT' ? deltaNormal : -deltaNormal;
                    const cell = row.querySelector('.delta');
                    if (deltaRaw === 0) {
                        cell.textContent = '—';
                        row.classList.remove('changed');
                        return;
                    }
                    count++;
                    row.classList.add('changed');
                    if (deltaRaw > 0) { debit += deltaRaw; cell.textContent = 'D ' + fmt(deltaRaw); }
                    else { credit += -deltaRaw; cell.textContent = 'K ' + fmt(-deltaRaw); }
                });
                document.getElementById('sum-count').textContent = count;
                document.getElementById('sum-debit').textContent = fmt(debit);
                document.getElementById('sum-credit').textContent = fmt(credit);
                const diff = debit - credit;
                document.getElementById('sum-diff').textContent = diff === 0 ? '0,00 (seimbang)'
                    : (diff > 0 ? 'K ' : 'D ') + fmt(Math.abs(diff));
            }

            rows.forEach((row) => row.querySelector('input').addEventListener('input', recalc));
            recalc();

            document.getElementById('account-filter').addEventListener('input', (e) => {
                const q = e.target.value.trim().toLowerCase();
                rows.forEach((row) => { row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none'; });
                document.querySelectorAll('#balance-table .group-row').forEach((g) => { g.style.display = q ? 'none' : ''; });
            });
        })();
    </script>
@endsection

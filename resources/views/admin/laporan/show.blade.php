@extends('layouts.app')

@section('title', $label)

@section('content')
    <style>
        .laporan-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .laporan-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: transparent; color: var(--pine-bright); border: 1px solid var(--pine); border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .potong-msg { background: var(--paper); border: 1px solid var(--line); border-left: 4px solid var(--brick); border-radius: 10px; padding: 12px 15px; font-size: 13px; line-height: 1.6; margin-bottom: 16px; }
        /* Sub total & total dibedakan latarnya — lihat catatan yang sama di
           prints/laporan/generic.blade.php. Memakai token tema supaya ikut
           benar di mode gelap. */
        .dt-table tr.baris-ringkasan td { background: var(--leaf); font-weight: 700; }
        .dt-table tr.baris-judul td { background: var(--pine); color: #fff; font-weight: 700; letter-spacing: .03em; }

        /* Popover "Cetak Neraca (Skontro)". Native <details> supaya tidak
           butuh JS library, dan blok isi tetap terikat di posisi tombolnya
           lewat position:relative + absolute pada isi. Diberi z-index tinggi
           supaya menutupi datatable di bawahnya waktu terbuka. */
        details.scontro-popover { position: relative; }
        details.scontro-popover > summary { list-style: none; cursor: pointer; }
        details.scontro-popover > summary::-webkit-details-marker { display: none; }
        details.scontro-popover[open] .scontro-panel {
            position: absolute; top: calc(100% + 6px); right: 0; z-index: 40;
            background: var(--surface); border: 1px solid var(--line); border-radius: 12px;
            padding: 14px 16px; box-shadow: var(--shadow); min-width: 320px;
        }
        .scontro-panel h4 { margin: 0 0 10px; font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .scontro-panel .field { display: block; margin-bottom: 8px; font-size: 13px; }
        .scontro-panel .field input[type=radio] { margin-right: 6px; }
        .scontro-panel select {
            width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px;
            font-size: 13px; margin-top: 4px;
        }
        .scontro-panel select:disabled { opacity: .5; background: var(--paper); }
        .scontro-panel .hint { font-size: 11.5px; color: var(--muted); margin: 6px 0 12px; line-height: 1.45; }
        .scontro-panel .row-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px; }
    </style>

    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="laporan-header">
        <div>
            <p style="margin: 0 0 4px; font-size: 12px;"><a href="{{ route('admin.laporan.index') }}" style="color: var(--muted); text-decoration: none;">&larr; Menu Laporan</a></p>
            <h2 style="margin: 0;">{{ $label }}</h2>
        </div>
        <div class="laporan-actions">
            {{-- Cetak khusus bentuk skontro (T-form Aset di kiri, Kewajiban+
                 Modal di kanan) — tampil hanya untuk dua modul Saldo Awal
                 COA. Tombol ini melewati route/print/blade sendiri karena
                 Export PDF generik meng-render datatable rata-tampilkan-
                 layar, bukan tata letak Neraca berformat skontro. Klik
                 tombol membuka pop-over pilihan cakupan (Konsolidasi vs
                 Cabang tertentu). Periode tidak ditampilkan sebagai
                 pilihan karena saldo awal terkunci pada `cutoff_date`
                 batch — controller mengambilnya otomatis. --}}
            @if (in_array($module, ['saldo_awal_neraca', 'saldo_awal_neraca_saldo']))
                @php
                    // Daftar cabang yang boleh diakses user. Kalau super
                    // admin (`allowedBranchIds() === null`), tampilkan
                    // semua cabang aktif.
                    $userAllowedIds = optional(auth()->user())->allowedBranchIds() ?? null;
                    $branchesForScontro = \App\Models\Branch::query()
                        ->where('is_active', true)
                        ->when(is_array($userAllowedIds), fn ($q) => $q->whereIn('id', $userAllowedIds))
                        ->orderBy('name')
                        ->get();
                @endphp
                <details class="scontro-popover">
                    <summary class="btn-secondary">Cetak Neraca (Skontro) ▾</summary>
                    <form class="scontro-panel" action="{{ route('admin.laporan.saldo-awal-neraca.print-scontro') }}" method="GET" target="_blank">
                        <h4>Pilih Cakupan</h4>
                        <label class="field">
                            <input type="radio" name="cakupan" value="konsolidasi" checked
                                   onchange="this.form.branch_id.disabled = true; this.form.branch_id.value = '';">
                            Konsolidasi Seluruh Cabang
                        </label>
                        <label class="field">
                            <input type="radio" name="cakupan" value="cabang"
                                   onchange="this.form.branch_id.disabled = false; this.form.branch_id.focus();">
                            Cabang tertentu:
                        </label>
                        <select name="branch_id" disabled>
                            <option value="">— pilih cabang —</option>
                            @foreach ($branchesForScontro as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <p class="hint">
                            Periode otomatis mengikuti <em>cutoff_date</em> batch saldo awal
                            yang sudah dikunci — tidak bisa dipilih di sini karena angkanya
                            sudah terikat ke tanggal cutoff-nya.
                        </p>
                        <div class="row-actions">
                            <button type="submit" class="btn-primary">Cetak PDF</button>
                        </div>
                    </form>
                </details>
            @endif
            <a href="{{ route('admin.laporan.export-pdf', $module) }}" class="btn-secondary" data-export>Export PDF</a>
            <a href="{{ route('admin.laporan.export-excel', $module) }}" class="btn-primary" data-export>Export Excel</a>
        </div>
    </div>

    @if ($dipotong ?? false)
        <div class="potong-msg">
            Laporan ini berisi <strong>{{ number_format($jumlahPenuh, 0, ',', '.') }} baris</strong>.
            Layar hanya menampilkan <strong>{{ number_format($maksBarisLayar, 0, ',', '.') }} baris pertama</strong>
            supaya halamannya tetap bisa dibuka dan disaring.
            <br>
            <strong>Export PDF dan Export Excel tetap memuat seluruh baris</strong> yang lolos saringan —
            pemotongan ini hanya berlaku di layar. Persempit dengan pencarian atau filter untuk melihat
            bagian yang Anda cari.
        </div>
    @endif

    <div class="dt-wrap" data-dt @if ($dateColumn) data-date-column="{{ $dateColumn }}" @endif>
        <div class="dt-toolbar">
            <input type="search" class="dt-search" placeholder="Cari di semua kolom...">

            @if ($dateColumn)
                <div class="dt-period">
                    <span>Periode</span>
                    <input type="date" class="dt-date-from" aria-label="Dari tanggal">
                    <span>s/d</span>
                    <input type="date" class="dt-date-to" aria-label="Sampai tanggal">
                </div>
            @endif

            @foreach ($filterable as $filterColumn)
                <select class="dt-filter" data-filter-column="{{ $filterColumn }}">
                    <option value="">{{ $columns[$filterColumn] ?? $filterColumn }} — Semua</option>
                    @foreach ($rows->pluck($filterColumn)->filter()->unique()->sort()->values() as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            @endforeach

            <span class="dt-count"></span>
        </div>

        <div class="dt-table-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        @foreach ($columns as $key => $columnLabel)
                            <th>{{ $columnLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class([
                            'baris-ringkasan' => ($row['_gaya'] ?? null) === 'ringkasan',
                            'baris-judul' => ($row['_gaya'] ?? null) === 'judul',
                        ])>
                            @foreach ($columns as $key => $columnLabel)
                                <td data-column="{{ $key }}">{{ $row[$key] ?? '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="dt-empty" @if (! $rows->isEmpty()) hidden @endif>
            {{ $rows->isEmpty() ? 'Belum ada data untuk laporan ini.' : 'Tidak ada data yang cocok dengan pencarian/filter.' }}
        </p>
    </div>

    {{--
        Saringan datatable bekerja di sisi klien, jadi server tidak tahu apa yang
        sedang tampil. Keadaan filter dititipkan ke URL ekspor saat tombolnya
        ditekan, lalu diterapkan ulang di server dengan aturan yang sama —
        tanpa ini, cetakan selalu berisi seluruh data meski layarnya tersaring.
    --}}
    <script>
        (function () {
            var wrap = document.querySelector('.dt-wrap[data-dt]');
            if (!wrap) { return; }

            document.querySelectorAll('[data-export]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    var p = new URLSearchParams();

                    var cari = (wrap.querySelector('.dt-search') || {}).value || '';
                    if (cari.trim() !== '') { p.set('cari', cari.trim()); }

                    wrap.querySelectorAll('.dt-filter').forEach(function (select) {
                        if (select.value !== '') { p.set('f_' + select.dataset.filterColumn, select.value); }
                    });

                    var dari = (wrap.querySelector('.dt-date-from') || {}).value || '';
                    var sampai = (wrap.querySelector('.dt-date-to') || {}).value || '';
                    if (dari !== '') { p.set('dari', dari); }
                    if (sampai !== '') { p.set('sampai', sampai); }

                    var qs = p.toString();
                    if (qs === '') { return; }

                    event.preventDefault();
                    window.location = link.href + (link.href.indexOf('?') === -1 ? '?' : '&') + qs;
                });
            });
        })();
    </script>
@endsection

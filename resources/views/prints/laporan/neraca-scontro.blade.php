@extends('prints.layout')

@section('title', $title ?? 'Neraca (Bentuk Skontro)')

@section('print-content')
    @php
        use App\Models\ChartOfAccount;

        // Muat SELURUH akun NERACA (postable + header non-postable). Header
        // account (`is_postable=false`) tidak ada di $balanceByCode karena
        // engine hanya mengembalikan leaf, tapi dibutuhkan sebagai baris
        // sub-total per kelompok (Aset Lancar / Kas / Bank / dst) —
        // sebagaimana lazim dicetak di Neraca skontro koperasi (contoh:
        // "Posisi_Keuangan" export sistem lama).
        $allCoa = ChartOfAccount::query()
            ->where('statement', 'NERACA')
            ->orderBy('code')
            ->get();

        // Indeks anak per parent_code. Root (parent_code kosong) dikumpulkan
        // di kunci '__ROOT__' supaya tidak bentrok dengan kode akun asli.
        $childrenByParent = $allCoa->groupBy(fn ($a) => (string) ($a->parent_code ?: '__ROOT__'));

        // Roll-up saldo: leaf pakai $balanceByCode (sudah "positive-natural"
        // dari GeneralLedgerService::balanceFor()), non-leaf jumlah rekursif
        // anak-anaknya. Untuk kontra-asset (Penyisihan Piutang / Akumulasi
        // Penyusutan) yang normal_balance=KREDIT tapi type=ASET, tandanya
        // dibalik ke NEGATIF supaya muncul mengurangi sisi Aset — persis
        // seperti Excel contoh koperasi ("Penyisihan Piutang: -360jt"),
        // yang tidak akan seimbang kalau angkanya positif.
        $balanceOf = function (ChartOfAccount $account) use (&$balanceOf, $childrenByParent, $balanceByCode) {
            if ($account->is_postable) {
                $raw = (float) ($balanceByCode[$account->code] ?? 0);
                $isContra = ($account->type === 'ASET' && $account->normal_balance === 'KREDIT')
                    || (in_array($account->type, ['LIABILITAS', 'EKUITAS']) && $account->normal_balance === 'DEBIT');
                return $isContra ? -$raw : $raw;
            }
            $sum = 0.0;
            foreach ($childrenByParent->get($account->code, collect()) as $child) {
                $sum += $balanceOf($child);
            }
            return $sum;
        };

        // Tree walk: hasilkan daftar baris ['level' => int, 'account' =>
        // COA, 'amount' => float]. Level dipakai untuk indent kolom
        // "Keterangan".
        $walk = function (ChartOfAccount $node, int $level = 0) use (&$walk, $childrenByParent, $balanceOf) {
            $out = [['level' => $level, 'account' => $node, 'amount' => $balanceOf($node)]];
            foreach ($childrenByParent->get($node->code, collect()) as $child) {
                foreach ($walk($child, $level + 1) as $row) {
                    $out[] = $row;
                }
            }
            return $out;
        };

        // Skip type-root sintetis (kode 1000/2000/3000 dari seeder default,
        // atau akun apa pun yang nama-nya sama persis dengan tipenya —
        // "ASET"/"LIABILITAS"/"EKUITAS" — dan bertindak sebagai wadah saja).
        // Kalau sebuah koperasi TIDAK memakai type-root itu (mis. Aset
        // Lancar langsung berada di level teratas dengan parent_code=null),
        // fallback: pakai akun itu sendiri sebagai level 0.
        $topRoots = $childrenByParent->get('__ROOT__', collect());
        $isSynthTypeRoot = fn (ChartOfAccount $r) => ! $r->is_postable
            && strcasecmp((string) $r->name, (string) $r->type) === 0
            && $childrenByParent->has($r->code);

        $rootsFor = function (array $types) use ($topRoots, $childrenByParent, $isSynthTypeRoot) {
            $out = collect();
            foreach ($topRoots->whereIn('type', $types) as $r) {
                if ($isSynthTypeRoot($r)) {
                    foreach ($childrenByParent->get($r->code) as $child) {
                        $out->push($child);
                    }
                    continue;
                }
                $out->push($r);
            }
            return $out;
        };

        $aktivaLines = [];
        foreach ($rootsFor(['ASET']) as $r) {
            foreach ($walk($r, 0) as $row) {
                $aktivaLines[] = $row;
            }
        }
        $pasivaLines = [];
        foreach ($rootsFor(['LIABILITAS', 'EKUITAS']) as $r) {
            foreach ($walk($r, 0) as $row) {
                $pasivaLines[] = $row;
            }
        }

        // Total sisi = jumlah amount di level 0 (bukan menjumlah leaf) —
        // supaya SUB TOTAL yang sudah dihitung roll-up dipakai apa adanya,
        // tanpa risiko dobel hitung kontra.
        $totalAktiva = collect($aktivaLines)->where('level', 0)->sum('amount');
        $totalPasiva = collect($pasivaLines)->where('level', 0)->sum('amount');
        $isBalanced = abs($totalAktiva - $totalPasiva) < 0.005;

        // Samakan panjang kedua sisi supaya kolom kiri-kanan sejajar dalam
        // satu tabel 4-kolom (Keterangan|Jumlah × 2), meniru layout Excel
        // contoh dari koperasi. Yang lebih pendek dipadding dengan baris
        // kosong.
        $maxRows = max(count($aktivaLines), count($pasivaLines));

        $meta = $meta ?? [];
        $petugas = $meta['petugas'] ?? optional(auth()->user())->name ?? '-';
        $tglCetak = $meta['tgl_cetak'] ?? now();
        if (! $tglCetak instanceof \DateTimeInterface) {
            $tglCetak = \Illuminate\Support\Carbon::parse($tglCetak);
        }

        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    @endphp

    <style>
        .n-title {
            text-align: center; font-size: 14pt; font-weight: 700;
            margin: 0 0 2px; letter-spacing: 0.5px;
        }
        .n-sub {
            text-align: center; font-size: 9.5pt; margin: 0 0 8px; color: #3D5A4B;
        }

        .n-meta { width: 100%; margin: 0 0 10px; font-size: 9pt; border-collapse: collapse; }
        .n-meta td { padding: 2px 4px; vertical-align: top; }
        .n-meta .k { width: 90px; }
        .n-meta .s { width: 8px; text-align: center; }

        .scontro {
            width: 100%; border-collapse: collapse; font-size: 9pt;
            table-layout: fixed;
        }
        .scontro th, .scontro td {
            border: 1px solid #8FA098; padding: 3px 6px; vertical-align: top;
        }
        .scontro thead th {
            background: #11543B; color: #fff; text-align: center; font-weight: 700;
            letter-spacing: 0.3px; font-size: 9.5pt;
        }
        .scontro thead tr.sub th {
            background: #E3EDE6; color: #11543B; font-size: 9pt; font-weight: 700;
            padding: 2px 6px;
        }
        .c-ket { width: 35%; }
        .c-jml { width: 15%; text-align: right; font-variant-numeric: tabular-nums; }

        /* Indent berjenjang. Level 0 = header kelompok (Aset Lancar, Modal, ...),
           dicetak tebal & kapital. Level 1 = akun induk (Kas, Bank, Piutang,
           Dana-Dana SHU, ...). Level 2+ = leaf (rekening/detail akun). */
        .lv-0 { padding-left: 2px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.2px; }
        .lv-0.c-jml { font-weight: 700; }
        .lv-1 { padding-left: 14px; }
        .lv-1.c-jml { font-weight: 600; }
        .lv-2 { padding-left: 28px; }
        .lv-3 { padding-left: 42px; }
        .lv-blank td { border-color: #DDE5DF; color: transparent; }
        .lv-blank td:before { content: "\00a0"; }

        .row-total td {
            background: #11543B; color: #fff; font-weight: 700; font-size: 10pt;
            letter-spacing: 0.3px;
        }
        .row-total .c-jml { font-size: 10.5pt; }

        .bal-note { margin-top: 8px; text-align: center; font-size: 9pt; color: #3D5A4B; }
        .bal-note.warn { color: #A63A2F; font-weight: 700; }
    </style>

    <h2 class="n-title">{{ strtoupper($title ?? 'NERACA (BENTUK SKONTRO)') }}</h2>
    @if (! empty($meta['sub_title']))
        <p class="n-sub">{{ $meta['sub_title'] }}</p>
    @endif

    <table class="n-meta">
        <tr>
            <td class="k">Periode</td><td class="s">:</td>
            <td>{{ $meta['periode'] ?? '-' }}</td>
            <td class="k">Petugas</td><td class="s">:</td>
            <td>{{ $petugas }}</td>
        </tr>
        <tr>
            <td class="k">Cabang</td><td class="s">:</td>
            <td>{{ $meta['cabang'] ?? '-' }}</td>
            <td class="k">Tgl. Dicetak</td><td class="s">:</td>
            <td>{{ $tglCetak->format('d-m-Y H:i') }}</td>
        </tr>
    </table>

    <table class="scontro">
        <thead>
            <tr>
                <th colspan="2">ASET</th>
                <th colspan="2">KEWAJIBAN DAN MODAL</th>
            </tr>
            <tr class="sub">
                <th class="c-ket" style="text-align:left;">Keterangan</th>
                <th class="c-jml">Jumlah</th>
                <th class="c-ket" style="text-align:left;">Keterangan</th>
                <th class="c-jml">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRows; $i++)
                @php
                    $l = $aktivaLines[$i] ?? null;
                    $r = $pasivaLines[$i] ?? null;
                @endphp
                <tr>
                    @if ($l)
                        <td class="c-ket lv-{{ min($l['level'], 3) }}">{{ $l['account']->name }}</td>
                        <td class="c-jml lv-{{ min($l['level'], 3) }}">{{ $fmt($l['amount']) }}</td>
                    @else
                        <td class="c-ket lv-blank"></td>
                        <td class="c-jml lv-blank"></td>
                    @endif
                    @if ($r)
                        <td class="c-ket lv-{{ min($r['level'], 3) }}">{{ $r['account']->name }}</td>
                        <td class="c-jml lv-{{ min($r['level'], 3) }}">{{ $fmt($r['amount']) }}</td>
                    @else
                        <td class="c-ket lv-blank"></td>
                        <td class="c-jml lv-blank"></td>
                    @endif
                </tr>
            @endfor
            <tr class="row-total">
                <td>TOTAL ASET</td>
                <td class="c-jml">{{ $fmt($totalAktiva) }}</td>
                <td>TOTAL KEWAJIBAN DAN MODAL</td>
                <td class="c-jml">{{ $fmt($totalPasiva) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="bal-note {{ $isBalanced ? '' : 'warn' }}">
        @if ($isBalanced)
            &#10003; Neraca SEIMBANG &mdash; Total Aset = Total Kewajiban dan Modal = Rp {{ $fmt($totalAktiva) }}
        @else
            &#9888; NERACA TIDAK SEIMBANG &mdash;
            Aset Rp {{ $fmt($totalAktiva) }} vs Kewajiban+Modal Rp {{ $fmt($totalPasiva) }}
            (selisih Rp {{ $fmt(abs($totalAktiva - $totalPasiva)) }})
        @endif
    </p>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_keuangan'])
@endsection

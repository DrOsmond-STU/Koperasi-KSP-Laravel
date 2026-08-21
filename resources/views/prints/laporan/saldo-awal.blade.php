@extends('prints.layout')

@section('title', 'Laporan Saldo Awal')

@section('print-content')
    @php
        $b = $batch;
        $cutoff = $b->cutoff_date;

        $totalDebit = $trialBalance->sum('debit');
        $totalKredit = $trialBalance->sum('kredit');

        $totalAktiva = $neracaAktiva->sum(fn ($r) => $r['debit'] - $r['kredit']);
        $totalKewajiban = $neracaKewajiban->sum(fn ($r) => $r['kredit'] - $r['debit']);
        $totalEkuitas = $neracaEkuitas->sum(fn ($r) => $r['kredit'] - $r['debit']);

        $totalPokokPinjaman = $b->loans->sum('original_principal');
        $totalOutstandingPokok = $b->loans->sum('outstanding_principal');
        $totalOutstandingJasa = $b->loans->sum('outstanding_interest');
        $totalDibayarkanLegacy = max(0, $totalPokokPinjaman - $totalOutstandingPokok);

        $totalSimpanan = $b->savings->sum('balance');
    @endphp

    <h2 style="font-size:14pt; margin:0 0 2px; text-align:center; letter-spacing:0.5px;">LAPORAN SALDO AWAL</h2>
    <p style="font-size:10pt; margin:0 0 3px; text-align:center;">
        <strong>Cabang: {{ $b->branch->name }}</strong>
    </p>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 14px; text-align:center;">
        Tanggal Cut-off: <strong>{{ $cutoff->translatedFormat('d M Y') }}</strong>
        — Status Batch: <strong>{{ $b->status === 'locked' ? 'Terkunci' : 'Draft' }}</strong>
    </p>

    {{-- ============================================================ --}}
    {{--  SEKSI 1: NERACA SALDO AWAL (Posisi Keuangan)                  --}}
    {{-- ============================================================ --}}
    <h3 style="font-size:11pt; margin:14px 0 6px; background:#F4F7F4; padding:6px 10px; border-radius:6px;">1. Neraca Saldo Awal (Posisi Keuangan)</h3>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:90px;">Kode</th>
                <th>Nama Akun</th>
                <th style="width:120px; text-align:right;">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#F4F7F4;"><td colspan="3" style="font-weight:700;">AKTIVA</td></tr>
            @foreach ($neracaAktiva as $r)
                @php $val = $r['debit'] - $r['kredit']; @endphp
                <tr>
                    <td>{{ $r['account']->code }}</td>
                    <td>{{ $r['account']->name }}</td>
                    <td style="text-align:right;">{{ number_format($val, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr style="background:#F4F7F4; font-weight:700;">
                <td colspan="2" style="text-align:right;">Total Aktiva</td>
                <td style="text-align:right;">{{ number_format($totalAktiva, 0, ',', '.') }}</td>
            </tr>

            <tr style="background:#F4F7F4;"><td colspan="3" style="font-weight:700; padding-top:8px;">KEWAJIBAN</td></tr>
            @foreach ($neracaKewajiban as $r)
                @php $val = $r['kredit'] - $r['debit']; @endphp
                <tr>
                    <td>{{ $r['account']->code }}</td>
                    <td>{{ $r['account']->name }}</td>
                    <td style="text-align:right;">{{ number_format($val, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr style="background:#F4F7F4; font-weight:700;">
                <td colspan="2" style="text-align:right;">Total Kewajiban</td>
                <td style="text-align:right;">{{ number_format($totalKewajiban, 0, ',', '.') }}</td>
            </tr>

            <tr style="background:#F4F7F4;"><td colspan="3" style="font-weight:700; padding-top:8px;">EKUITAS</td></tr>
            @foreach ($neracaEkuitas as $r)
                @php $val = $r['kredit'] - $r['debit']; @endphp
                <tr>
                    <td>{{ $r['account']->code }}</td>
                    <td>{{ $r['account']->name }}</td>
                    <td style="text-align:right;">{{ number_format($val, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr style="background:#F4F7F4; font-weight:700;">
                <td colspan="2" style="text-align:right;">Total Ekuitas</td>
                <td style="text-align:right;">{{ number_format($totalEkuitas, 0, ',', '.') }}</td>
            </tr>
            <tr style="background:#E9F1EC; font-weight:700;">
                <td colspan="2" style="text-align:right;">Total Kewajiban + Ekuitas</td>
                <td style="text-align:right;">{{ number_format($totalKewajiban + $totalEkuitas, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ============================================================ --}}
    {{--  SEKSI 2: NERACA SALDO (Trial Balance)                        --}}
    {{-- ============================================================ --}}
    <h3 style="font-size:11pt; margin:14px 0 6px; background:#F4F7F4; padding:6px 10px; border-radius:6px; page-break-before: always;">2. Neraca Saldo (Trial Balance)</h3>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:90px;">Kode</th>
                <th>Nama Akun</th>
                <th style="width:120px; text-align:right;">Debit (Rp)</th>
                <th style="width:120px; text-align:right;">Kredit (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($trialBalance as $r)
                <tr>
                    <td>{{ $r['account']->code }}</td>
                    <td>{{ $r['account']->name }}</td>
                    <td style="text-align:right;">{{ $r['debit'] > 0 ? number_format($r['debit'], 0, ',', '.') : '' }}</td>
                    <td style="text-align:right;">{{ $r['kredit'] > 0 ? number_format($r['kredit'], 0, ',', '.') : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color:#5C6E64;">Belum ada baris COA di batch ini.</td></tr>
            @endforelse
            <tr style="background:#F4F7F4; font-weight:700;">
                <td colspan="2" style="text-align:right;">Total</td>
                <td style="text-align:right;">{{ number_format($totalDebit, 0, ',', '.') }}</td>
                <td style="text-align:right;">{{ number_format($totalKredit, 0, ',', '.') }}</td>
            </tr>
            <tr @if (abs($totalDebit - $totalKredit) > 0.5) style="background:#FDECEC; font-weight:700;" @else style="background:#E9F1EC; font-weight:700;" @endif>
                <td colspan="4" style="text-align:center;">
                    @if (abs($totalDebit - $totalKredit) > 0.5)
                        ⚠ SELISIH: {{ number_format(abs($totalDebit - $totalKredit), 0, ',', '.') }} — trial balance TIDAK seimbang.
                    @else
                        ✓ Debit = Kredit (seimbang).
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    {{-- ============================================================ --}}
    {{--  SEKSI 3: DAFTAR PINJAMAN (aktif)                             --}}
    {{-- ============================================================ --}}
    <h3 style="font-size:11pt; margin:14px 0 6px; background:#F4F7F4; padding:6px 10px; border-radius:6px; page-break-before: always;">3. Daftar Pinjaman Aktif</h3>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:130px;">No. Pinjaman Lama</th>
                <th>Anggota</th>
                <th>Produk</th>
                <th style="width:75px;">Tgl Cair</th>
                <th style="width:60px;">Tenor</th>
                <th style="width:70px;">Sisa Tenor</th>
                <th style="width:100px; text-align:right;">Pokok Asal (Rp)</th>
                <th style="width:100px; text-align:right;">Sisa Pokok (Rp)</th>
                <th style="width:100px; text-align:right;">Sisa Jasa (Rp)</th>
                <th style="width:70px;">Kolek.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($b->loans as $loan)
                <tr>
                    <td>{{ $loan->external_loan_number ?? '—' }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>{{ $loan->loanProduct->name }}</td>
                    <td>{{ $loan->disbursement_date->translatedFormat('d M Y') }}</td>
                    <td>{{ $loan->tenor_days }} hari</td>
                    <td>{{ $loan->remaining_tenor_days }} hari</td>
                    <td style="text-align:right;">{{ number_format((float) $loan->original_principal, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $loan->outstanding_principal, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $loan->outstanding_interest, 0, ',', '.') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $loan->collectibility)) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center; color:#5C6E64;">Belum ada data pinjaman di batch ini.</td></tr>
            @endforelse
            @if ($b->loans->isNotEmpty())
                <tr style="background:#F4F7F4; font-weight:700;">
                    <td colspan="6" style="text-align:right;">TOTAL</td>
                    <td style="text-align:right;">{{ number_format($totalPokokPinjaman, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($totalOutstandingPokok, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($totalOutstandingJasa, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ============================================================ --}}
    {{--  SEKSI 4: DAFTAR SIMPANAN                                     --}}
    {{-- ============================================================ --}}
    <h3 style="font-size:11pt; margin:14px 0 6px; background:#F4F7F4; padding:6px 10px; border-radius:6px; page-break-before: always;">4. Daftar Simpanan</h3>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:130px;">No. Rek. Lama</th>
                <th>Anggota</th>
                <th>Produk Simpanan</th>
                <th style="width:150px; text-align:right;">Saldo (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($b->savings as $sav)
                <tr>
                    <td>{{ $sav->account_number ?? '—' }}</td>
                    <td>{{ $sav->member->name }}</td>
                    <td>{{ $sav->savingsProduct->name }}</td>
                    <td style="text-align:right;">{{ number_format((float) $sav->balance, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color:#5C6E64;">Belum ada data simpanan di batch ini.</td></tr>
            @endforelse
            @if ($b->savings->isNotEmpty())
                <tr style="background:#F4F7F4; font-weight:700;">
                    <td colspan="3" style="text-align:right;">TOTAL</td>
                    <td style="text-align:right;">{{ number_format($totalSimpanan, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ============================================================ --}}
    {{--  SEKSI 5: HISTORIS PEMBAYARAN (pokok yang sudah terbayar      --}}
    {{--            sebelum cut-off)                                    --}}
    {{-- ============================================================ --}}
    <h3 style="font-size:11pt; margin:14px 0 6px; background:#F4F7F4; padding:6px 10px; border-radius:6px; page-break-before: always;">5. Historis Pembayaran Pinjaman (sebelum cut-off)</h3>
    <p style="font-size:9pt; color:#5C6E64; margin: 0 0 8px;">
        Kolom "Sudah Terbayar" dihitung dari selisih Pokok Asal − Sisa Pokok pada snapshot cut-off.
    </p>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:130px;">No. Pinjaman Lama</th>
                <th>Anggota</th>
                <th style="width:75px;">Tgl Cair</th>
                <th style="width:120px; text-align:right;">Pokok Asal (Rp)</th>
                <th style="width:120px; text-align:right;">Sisa Pokok (Rp)</th>
                <th style="width:120px; text-align:right;">Sudah Terbayar (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($b->loans as $loan)
                @php $sudahBayar = max(0, (float) $loan->original_principal - (float) $loan->outstanding_principal); @endphp
                <tr>
                    <td>{{ $loan->external_loan_number ?? '—' }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>{{ $loan->disbursement_date->translatedFormat('d M Y') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $loan->original_principal, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $loan->outstanding_principal, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($sudahBayar, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; color:#5C6E64;">Belum ada data pinjaman.</td></tr>
            @endforelse
            @if ($b->loans->isNotEmpty())
                <tr style="background:#F4F7F4; font-weight:700;">
                    <td colspan="3" style="text-align:right;">TOTAL</td>
                    <td style="text-align:right;">{{ number_format($totalPokokPinjaman, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($totalOutstandingPokok, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($totalDibayarkanLegacy, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ============================================================ --}}
    {{--  SEKSI 6: JADWAL ANGSURAN (opening_balance_installments)      --}}
    {{-- ============================================================ --}}
    <h3 style="font-size:11pt; margin:14px 0 6px; background:#F4F7F4; padding:6px 10px; border-radius:6px; page-break-before: always;">6. Jadwal Angsuran Sisa</h3>
    <table class="data-table" style="margin-bottom: 14px;">
        <thead>
            <tr>
                <th style="width:130px;">No. Pinjaman Lama</th>
                <th>Anggota</th>
                <th style="width:70px;">Angs. Ke</th>
                <th style="width:80px;">Jatuh Tempo</th>
                <th style="width:100px; text-align:right;">Pokok (Rp)</th>
                <th style="width:100px; text-align:right;">Jasa (Rp)</th>
                <th style="width:100px; text-align:right;">Denda (Rp)</th>
                <th style="width:100px; text-align:right;">Jumlah (Rp)</th>
                <th style="width:70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $installments = $b->loans->flatMap(fn ($l) => $l->installments->map(fn ($i) => ['loan' => $l, 'inst' => $i]))
                    ->sortBy(fn ($r) => $r['inst']->due_date?->timestamp);
                $sumPokok = 0; $sumJasa = 0; $sumDenda = 0;
            @endphp
            @forelse ($installments as $row)
                @php
                    $i = $row['inst'];
                    $l = $row['loan'];
                    $jumlah = (float) $i->principal_amount + (float) $i->interest_amount + (float) $i->penalty_amount;
                    $sumPokok += (float) $i->principal_amount;
                    $sumJasa += (float) $i->interest_amount;
                    $sumDenda += (float) $i->penalty_amount;
                @endphp
                <tr>
                    <td>{{ $l->external_loan_number ?? '—' }}</td>
                    <td>{{ $l->member->name }}</td>
                    <td>{{ $i->installment_number }}</td>
                    <td>{{ $i->due_date->translatedFormat('d M Y') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $i->principal_amount, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $i->interest_amount, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format((float) $i->penalty_amount, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($jumlah, 0, ',', '.') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $i->status)) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#5C6E64;">Belum ada baris jadwal angsuran.</td></tr>
            @endforelse
            @if ($installments->isNotEmpty())
                <tr style="background:#F4F7F4; font-weight:700;">
                    <td colspan="4" style="text-align:right;">TOTAL</td>
                    <td style="text-align:right;">{{ number_format($sumPokok, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($sumJasa, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($sumDenda, 0, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format($sumPokok + $sumJasa + $sumDenda, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <p style="font-size:8pt; color:#9AA9A0; margin-top:14px;">
        Sumber data: OpeningBalanceBatch #{{ $b->id }} (cabang {{ $b->branch->name }}, cut-off {{ $cutoff->translatedFormat('d M Y') }}).
        Angka Neraca & Trial Balance diambil dari <em>opening_balance_coa</em>; rincian pinjaman/simpanan/angsuran dari sub-modul terkait.
    </p>

    @include('prints.partials.signature-block', ['documentGroup' => 'laporan_saldo_awal'])
@endsection

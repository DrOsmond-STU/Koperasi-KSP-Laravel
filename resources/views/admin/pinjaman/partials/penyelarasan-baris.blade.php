{{--
    Tabel rincian tiap baris jadwal: nilai SEBELUM → SESUDAH penyelarasan.
    $rincian : array<int, array{no, jatuh_tempo, pokok, jasa, lama, baru, berubah}>
    $tabelId : id unik (untuk filter "hanya baris berubah")
--}}
@php
    $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
    $labelStatus = fn (?string $s) => match ($s) {
        'lunas' => 'Lunas',
        'sebagian' => 'Sebagian',
        'belum_bayar' => 'Belum bayar',
        null => '—',
        default => ucfirst($s),
    };
    $sel = function (?array $lama, ?array $baru, string $k) use ($rp) {
        if ($baru === null) {
            return '<span class="tenang">—</span>';
        }
        if ($lama === null) {
            return '<span class="tenang">baris baru</span><br><strong>'.$rp($baru[$k]).'</strong>';
        }
        $l = (float) $lama[$k]; $b = (float) $baru[$k];
        if (abs($l - $b) <= 0.005) {
            return '<span class="tenang">'.$rp($b).'</span>';
        }
        return '<s class="tenang">'.$rp($l).'</s><br><strong>'.$rp($b).'</strong>';
    };
@endphp

<table class="data-table rincian-baris" id="{{ $tabelId }}">
    <thead>
        <tr>
            <th class="num">Angs.</th>
            <th>Jatuh Tempo</th>
            <th class="num">Pokok Jadwal</th>
            <th class="num">Jasa Jadwal</th>
            <th class="num">Terbayar Pokok<br><span class="tenang">sebelum → sesudah</span></th>
            <th class="num">Terbayar Jasa<br><span class="tenang">sebelum → sesudah</span></th>
            <th class="num">Total Terbayar<br><span class="tenang">sebelum → sesudah</span></th>
            <th>Status<br><span class="tenang">sebelum → sesudah</span></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rincian as $b)
            <tr class="{{ $b['berubah'] ? 'baris-berubah' : 'baris-tetap' }}">
                <td class="num">{{ $b['no'] }}@if (! empty($b['baris_baru']))<br><span class="tenang">penyesuaian</span>@endif</td>
                <td>{{ $b['jatuh_tempo'] ? \Illuminate\Support\Carbon::parse($b['jatuh_tempo'])->translatedFormat('d M Y') : '-' }}</td>
                <td class="num">{{ $rp($b['pokok']) }}</td>
                <td class="num">{{ $rp($b['jasa']) }}</td>
                <td class="num">{!! $sel($b['lama'], $b['baru'], 'paid_principal_amount') !!}</td>
                <td class="num">{!! $sel($b['lama'], $b['baru'], 'paid_interest_amount') !!}</td>
                <td class="num">{!! $sel($b['lama'], $b['baru'], 'paid_amount') !!}</td>
                <td>
                    @if ($b['baru'] === null)
                        <span class="tenang">{{ $labelStatus($b['lama']['status']) }}</span>
                    @elseif ($b['lama'] === null)
                        <span class="tenang">baris baru</span><br><strong>{{ $labelStatus($b['baru']['status']) }}</strong>
                    @elseif ($b['lama']['status'] === $b['baru']['status'])
                        <span class="tenang">{{ $labelStatus($b['baru']['status']) }}</span>
                    @else
                        <s class="tenang">{{ $labelStatus($b['lama']['status']) }}</s><br><strong>{{ $labelStatus($b['baru']['status']) }}</strong>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">Tidak ada baris jadwal.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Ringkasan neraca saldo awal. $kolom = [judul => ringkasan] --}}
@php $rp = fn ($n) => 'Rp '.number_format((float) $n, 2, ',', '.'); @endphp
<table class="data-table ringkas">
    <thead>
        <tr><th></th>@foreach ($kolom as $judul => $r)<th class="num">{{ $judul }}</th>@endforeach</tr>
    </thead>
    <tbody>
        <tr><td>Total Aset</td>@foreach ($kolom as $r)<td class="num">{{ $rp($r['aset']) }}</td>@endforeach</tr>
        <tr><td>Total Kewajiban</td>@foreach ($kolom as $r)<td class="num">{{ $rp($r['kewajiban']) }}</td>@endforeach</tr>
        <tr><td>Total Ekuitas</td>@foreach ($kolom as $r)<td class="num">{{ $rp($r['ekuitas']) }}</td>@endforeach</tr>
        <tr><td>SHU (dari akun Laba/Rugi)</td>@foreach ($kolom as $r)<td class="num">{{ $rp($r['shu']) }}</td>@endforeach</tr>
        <tr><td><strong>Kewajiban + Ekuitas + SHU</strong></td>@foreach ($kolom as $r)<td class="num"><strong>{{ $rp($r['pasiva']) }}</strong></td>@endforeach</tr>
        <tr>
            <td><strong>Selisih (Aset − Pasiva)</strong></td>
            @foreach ($kolom as $r)
                <td class="num {{ abs($r['selisih']) >= 0.005 ? 'selisih-plus' : '' }}">{{ abs($r['selisih']) >= 0.005 ? $rp($r['selisih']) : '✓ seimbang' }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

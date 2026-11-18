{{-- Tabel kartu pinjaman (buku besar) — dipakai layar kartu & pratinjau koreksi.
     $k = hasil LoanBalanceCorrectionService::kartu(); $aksi = tampilkan tautan Koreksi;
     $sorot = id angsuran yang disorot (opsional). --}}
@php
    $rp = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', '.');
    $aksi = $aksi ?? false;
    $sorot = $sorot ?? [];
@endphp
<table class="data-table">
    <thead>
        <tr>
            <th>Tgl Transaksi</th>
            <th>Tgl Input</th>
            <th>Petugas</th>
            <th>No.</th>
            <th class="num">Jumlah</th>
            <th class="num">Pokok</th>
            <th class="num">Jasa</th>
            <th class="num">Denda</th>
            <th class="num">Sisa Pokok</th>
            <th class="num">Sisa Jasa</th>
            <th>Catatan</th>
            @if ($aksi)<th></th>@endif
        </tr>
    </thead>
    <tbody>
        <tr style="background: rgba(46,125,80,0.07); font-weight:700;">
            <td>{{ $k['tanggal_awal']?->translatedFormat('d M Y') ?? '—' }}</td>
            <td colspan="7">{{ $k['migrasi'] ? 'SALDO AWAL (migrasi)' : 'PENCAIRAN' }}</td>
            <td class="num">{{ $rp($k['pokok_awal']) }}</td>
            <td class="num">{{ $rp($k['jasa_awal']) }}</td>
            <td></td>
            @if ($aksi)<td></td>@endif
        </tr>
        @foreach ($k['baris'] as $b)
            @php $r = $b['repayment']; @endphp
            <tr style="{{ $b['batal'] ? 'color: var(--muted);' : '' }}{{ in_array($r->id, $sorot, true) ? 'background: rgba(255,191,0,0.15);' : ($b['catatan'] !== [] ? 'background: rgba(255,191,0,0.07);' : '') }}">
                <td>{{ \Illuminate\Support\Carbon::parse($b['tanggal'])->translatedFormat('d M Y') }}</td>
                <td class="tenang">{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                <td>{{ $r->createdBy->name ?? '-' }}</td>
                <td>#{{ $r->id }}</td>
                <td class="num">{!! $b['batal'] ? '<s>'.$rp($r->amount).'</s>' : $rp($r->amount) !!}</td>
                <td class="num">{!! $b['batal'] ? '<s>'.$rp($r->principal_portion).'</s>' : $rp($r->principal_portion) !!}</td>
                <td class="num">{!! $b['batal'] ? '<s>'.$rp($r->interest_portion).'</s>' : $rp($r->interest_portion) !!}</td>
                <td class="num">{!! $b['batal'] ? '<s>'.$rp($r->penalty_portion).'</s>' : $rp($r->penalty_portion) !!}</td>
                <td class="num {{ ($b['sisa_pokok'] ?? 0) < -0.5 ? 'selisih-plus' : '' }}">{{ $rp($b['sisa_pokok']) }}</td>
                <td class="num {{ ($b['sisa_jasa'] ?? 0) < -0.5 ? 'selisih-plus' : '' }}">{{ $rp($b['sisa_jasa']) }}</td>
                <td style="max-width:320px;">
                    @if ($b['batal'])
                        <span class="tenang">Dibatalkan {{ $r->cancelled_at?->translatedFormat('d M Y') }}{{ isset($pengguna[$r->cancelled_by]) ? ' oleh '.$pengguna[$r->cancelled_by] : '' }} — {{ $r->cancellation_reason }}</span>
                    @endif
                    @foreach ($b['catatan'] as $c)
                        <span class="warn-note" style="display:block;">⚠ {{ $c }}</span>
                    @endforeach
                    @if (! $b['batal'] && $r->description)
                        <span class="info-note" style="display:block;">{{ $r->description }}</span>
                    @endif
                </td>
                @if ($aksi)
                    <td>
                        @unless ($b['batal'])
                            <a class="tautan-kecil" href="{{ route('admin.pinjaman.perbaikan-saldo.koreksi', ['repayment' => $r, 'per' => request('per')]) }}">Koreksi</a>
                        @endunless
                    </td>
                @endif
            </tr>
        @endforeach
        <tr style="font-weight:700; border-top: 2px solid var(--line);">
            <td colspan="4">TOTAL angsuran yang berlaku</td>
            <td class="num">{{ $rp($k['total']['jumlah']) }}</td>
            <td class="num">{{ $rp($k['total']['pokok']) }}</td>
            <td class="num">{{ $rp($k['total']['jasa']) }}</td>
            <td class="num">{{ $rp($k['total']['denda']) }}</td>
            <td class="num {{ $k['sisa_pokok'] < -0.5 ? 'selisih-plus' : '' }}">{{ $rp($k['sisa_pokok']) }}</td>
            <td class="num {{ $k['sisa_jasa'] < -0.5 ? 'selisih-plus' : '' }}">{{ $rp($k['sisa_jasa']) }}</td>
            <td colspan="{{ $aksi ? 2 : 1 }}" class="tenang">Sisa akhir menurut buku besar</td>
        </tr>
    </tbody>
</table>

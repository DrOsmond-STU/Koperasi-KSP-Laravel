@extends('layouts.app')

@section('title', 'Rincian Riwayat Penyelarasan')

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    @php
        $rp = fn ($n) => $n === null ? '—' : 'Rp '.number_format((float) $n, 0, ',', '.');
    @endphp

    <p><a href="{{ route('admin.pinjaman.penyelarasan-jadwal.index') }}" class="tautan-kembali">← Kembali ke daftar penyelarasan</a></p>

    <h2>Rincian Penyelarasan {{ $alignment->created_at->translatedFormat('d M Y H:i') }}</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px;">
        Oleh {{ $alignment->performedBy->name ?? '-' }} ·
        {{ $alignment->loans_aligned }} pinjaman · {{ number_format($alignment->rows_changed, 0, ',', '.') }} baris jadwal diubah ·
        @if ($alignment->isReverted())
            <span style="color:var(--brick); font-weight:700;">DIBATALKAN {{ $alignment->reverted_at->translatedFormat('d M Y H:i') }} oleh {{ $alignment->revertedBy->name ?? '-' }}</span>
            — jadwal sudah dikembalikan ke nilai "sebelum".
        @else
            <span style="color:var(--ok); font-weight:700;">Berlaku</span>
        @endif
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    @forelse ($pinjaman as $p)
        <div class="panel">
            <h3 style="margin-top:0;">
                {{ $p['loan_number'] }} <span class="tenang">— {{ $p['anggota'] }}</span>
                @if ($p['loan'])
                    <a class="tautan-kecil" href="{{ route('admin.pinjaman.penyelarasan-jadwal.show', $p['loan']) }}">kondisi sekarang</a>
                @endif
            </h3>

            <table class="data-table ringkas">
                <thead>
                    <tr><th></th><th class="num">Sebelum</th><th class="num">Sesudah</th><th class="num">Perubahan</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Status pinjaman</td>
                        <td class="num">{{ $p['status_lama'] ? ucfirst($p['status_lama']) : '—' }}</td>
                        <td class="num"><strong>{{ $p['status_baru'] ? ucfirst($p['status_baru']) : '—' }}</strong></td>
                        <td class="num">{{ $p['status_lama'] === $p['status_baru'] ? '—' : 'berubah' }}</td>
                    </tr>
                    <tr>
                        <td>Sisa pokok menurut jadwal</td>
                        <td class="num">{{ $rp($p['sisa_pokok_jadwal_lama']) }}</td>
                        <td class="num"><strong>{{ $rp($p['sisa_pokok_jadwal_baru']) }}</strong></td>
                        <td class="num {{ $p['selisih_pokok'] > 0 ? 'selisih-plus' : ($p['selisih_pokok'] < 0 ? 'selisih-minus' : '') }}">
                            {{ $p['selisih_pokok'] > 0 ? '+' : '' }}{{ number_format($p['selisih_pokok'], 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td>Sisa jasa menurut jadwal</td>
                        <td class="num">{{ $rp($p['sisa_jasa_jadwal_lama']) }}</td>
                        <td class="num"><strong>{{ $rp($p['sisa_jasa_jadwal_baru']) }}</strong></td>
                        <td class="num {{ $p['selisih_jasa'] > 0 ? 'selisih-plus' : ($p['selisih_jasa'] < 0 ? 'selisih-minus' : '') }}">
                            {{ $p['selisih_jasa'] > 0 ? '+' : '' }}{{ number_format($p['selisih_jasa'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <h4 style="margin-bottom:6px;">Baris jadwal yang diubah ({{ $p['baris_berubah'] }})</h4>
            <p class="tenang" style="margin:0 0 10px;">
                Nilai yang dicoret adalah nilai sebelum penyelarasan; nilai tebal adalah nilai sesudahnya. Baris yang tidak berubah tidak dicatat.
            </p>
            @include('admin.pinjaman.partials.penyelarasan-baris', ['rincian' => $p['rincian'], 'tabelId' => 'riwayat-'.$p['loan_id']])
        </div>
    @empty
        <p class="tenang">Penyelarasan ini tidak memuat pinjaman.</p>
    @endforelse
@endsection

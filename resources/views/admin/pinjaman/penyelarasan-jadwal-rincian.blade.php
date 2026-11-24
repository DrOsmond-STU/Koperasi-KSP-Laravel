@extends('layouts.app')

@section('title', 'Rincian Penyelarasan — '.$loan->loan_number)

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    @php
        $rp = fn ($n) => $n === null ? '—' : 'Rp '.number_format((float) $n, 0, ',', '.');
        $berubah = collect($r['rincian'])->where('berubah', true)->count();
    @endphp

    <p><a href="{{ route('admin.pinjaman.penyelarasan-jadwal.index') }}" class="tautan-kembali">← Kembali ke daftar penyelarasan</a></p>

    <h2>Rincian Penyelarasan: {{ $loan->loan_number }}</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px;">
        {{ $loan->member->name ?? '-' }} · pinjaman {{ $r['migrasi'] ? 'migrasi (saldo awal)' : 'dicairkan di aplikasi' }}.
        Belum ada yang diubah — ini gambaran <em>sebelum</em> dan <em>sesudah</em> bila pinjaman ini diselaraskan.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="panel">
        <h3 style="margin-top:0;">Ringkasan</h3>
        <table class="data-table ringkas">
            <thead>
                <tr><th></th><th class="num">Sebelum</th><th class="num">Sesudah</th><th class="num">Perubahan</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Status pinjaman</td>
                    <td class="num">{{ ucfirst($r['status_lama']) }}</td>
                    <td class="num"><strong>{{ ucfirst($r['status_baru']) }}</strong></td>
                    <td class="num">{{ $r['status_lama'] === $r['status_baru'] ? '—' : 'berubah' }}</td>
                </tr>
                <tr>
                    <td>Sisa pokok menurut jadwal</td>
                    <td class="num">{{ $rp($r['sisa_pokok_jadwal']) }}</td>
                    <td class="num"><strong>{{ $rp($r['target_sisa_pokok']) }}</strong></td>
                    <td class="num {{ $r['selisih_pokok'] > 0 ? 'selisih-plus' : ($r['selisih_pokok'] < 0 ? 'selisih-minus' : '') }}">
                        {{ $r['selisih_pokok'] > 0 ? '+' : '' }}{{ number_format($r['selisih_pokok'], 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td>Sisa jasa menurut jadwal</td>
                    <td class="num">{{ $rp($r['sisa_jasa_jadwal']) }}</td>
                    <td class="num"><strong>{{ $rp($r['target_sisa_jasa']) }}</strong></td>
                    <td class="num {{ $r['selisih_jasa'] > 0 ? 'selisih-plus' : ($r['selisih_jasa'] < 0 ? 'selisih-minus' : '') }}">
                        {{ $r['selisih_jasa'] > 0 ? '+' : '' }}{{ number_format($r['selisih_jasa'], 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td>Baris jadwal yang berubah</td>
                    <td class="num tenang">{{ count($r['rincian']) }} baris</td>
                    <td class="num"><strong>{{ $berubah }} baris diubah</strong></td>
                    <td class="num tenang">{{ count($r['rincian']) - $berubah }} tetap</td>
                </tr>
            </tbody>
        </table>

        <h4 style="margin-bottom:6px;">Acuan buku besar (tidak diubah)</h4>
        <table class="data-table ringkas">
            <tbody>
                <tr><td>Pokok awal</td><td class="num">{{ $rp($r['pokok_awal']) }}</td><td>Jasa awal</td><td class="num">{{ $rp($r['jasa_awal']) }}</td></tr>
                <tr><td>Pokok dibayar (angsuran tidak dibatalkan)</td><td class="num">{{ $rp($r['pokok_bayar']) }}</td><td>Jasa dibayar</td><td class="num">{{ $rp($r['jasa_bayar']) }}</td></tr>
                <tr><td><strong>Sisa pokok buku besar</strong></td><td class="num"><strong>{{ $rp($r['target_sisa_pokok']) }}</strong></td><td><strong>Sisa jasa buku besar</strong></td><td class="num"><strong>{{ $r['sisa_jasa_buku'] === null ? '— (jasa jadwal dibiarkan)' : $rp($r['target_sisa_jasa']) }}</strong></td></tr>
            </tbody>
        </table>

        @foreach ($r['peringatan'] as $p)
            <p class="warn-note">⚠ {{ $p }}</p>
        @endforeach
        @if (! $r['perlu'])
            <p class="ok-note">✓ Jadwal pinjaman ini sudah selaras dengan buku besar.</p>
        @endif
    </div>

    <div class="panel">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <h3 style="margin:0;">Baris Jadwal: Sebelum → Sesudah</h3>
            <label style="font-size:13px; font-weight:400;">
                <input type="checkbox" id="hanya-berubah" checked> Tampilkan hanya baris yang berubah ({{ $berubah }})
            </label>
        </div>
        <p class="tenang" style="margin:6px 0 12px;">
            Nilai yang dicoret adalah nilai sekarang; nilai tebal adalah nilai sesudah diselaraskan. Pokok/jasa jadwal per angsuran tidak diubah.
        </p>
        @include('admin.pinjaman.partials.penyelarasan-baris', ['rincian' => $r['rincian'], 'tabelId' => 'rincian-'.$loan->id])
    </div>

    @if ($r['perlu'] && $r['bisa'])
        <div class="panel">
            <form method="POST" action="{{ route('admin.pinjaman.penyelarasan-jadwal.store') }}">
                @csrf
                <input type="hidden" name="loan_ids[]" value="{{ $loan->id }}">
                <div class="konfirmasi">
                    <input type="checkbox" name="konfirmasi" value="1" id="konfirmasi" required>
                    <label for="konfirmasi" style="font-weight:400;">
                        Saya sudah membaca rincian di atas dan menyetujui penyelarasan pinjaman {{ $loan->loan_number }}.
                        Jurnal tidak disentuh; bisa dibatalkan dari riwayat.
                    </label>
                </div>
                <button type="submit" class="btn-save"
                        onclick="return confirm('Selaraskan jadwal {{ $loan->loan_number }} dengan buku besar?');">
                    Selaraskan Pinjaman Ini
                </button>
            </form>
        </div>
    @elseif ($r['perlu'])
        <p class="error-msg">Pinjaman ini tidak bisa diselaraskan otomatis — lihat peringatan di atas.</p>
    @endif

    <script>
        (function () {
            var cb = document.getElementById('hanya-berubah');
            var tabel = document.getElementById('rincian-{{ $loan->id }}');
            function terapkan() { tabel.classList.toggle('hanya-berubah', cb.checked); }
            cb.addEventListener('change', terapkan);
            terapkan();
        })();
    </script>
@endsection

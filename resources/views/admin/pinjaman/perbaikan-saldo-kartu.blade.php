@extends('layouts.app')

@section('title', 'Kartu Pinjaman — '.$k['loan']->loan_number)

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    @php
        $loan = $k['loan'];
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $j = $k['jadwal'];
    @endphp

    <p><a href="{{ route('admin.pinjaman.perbaikan-saldo.index') }}" class="tautan-kembali">← Kembali ke Perbaikan Saldo Pinjaman</a></p>

    <h2>Kartu Pinjaman {{ $loan->loan_number }}</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px;">
        {{ $loan->member->name ?? '-' }} ({{ $loan->member->member_number ?? '-' }}) · {{ $loan->loanProduct->name ?? '-' }} ·
        plafon {{ $rp($loan->principal_amount) }} · tenor {{ $loan->tenor_days }} {{ $loan->tenor_unit }} ·
        status <span class="badge {{ $loan->status === 'lunas' ? 'badge-lunas' : 'badge-aktif' }}">{{ ucfirst($loan->status) }}</span>
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="panel">
        <form method="GET" action="{{ route('admin.pinjaman.perbaikan-saldo.show', $loan) }}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
            <label for="per" style="font-size:13px; font-weight:600;">Saldo per tanggal</label>
            <input type="date" id="per" name="per" value="{{ $k['per']->toDateString() }}" style="padding:7px 9px;">
            <button type="submit" class="btn-save">Tampilkan</button>
        </form>

        <table class="data-table ringkas">
            <thead>
                <tr><th></th><th class="num">Per {{ $k['per']->translatedFormat('d M Y') }}</th><th class="num">Sisa akhir (semua angsuran)</th><th class="num">Menurut jadwal</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Sisa pokok</strong></td>
                    <td class="num"><strong>{{ $rp($k['saldo_per']['pokok']) }}</strong></td>
                    <td class="num">{{ $rp($k['sisa_pokok']) }}</td>
                    <td class="num">{{ $j ? $rp($j['sisa_pokok_jadwal']) : '—' }}</td>
                </tr>
                <tr>
                    <td><strong>Sisa jasa</strong></td>
                    <td class="num"><strong>{{ $rp($k['saldo_per']['jasa']) }}</strong></td>
                    <td class="num">{{ $rp($k['sisa_jasa']) }}</td>
                    <td class="num">{{ $j ? $rp($j['sisa_jasa_jadwal']) : '—' }}</td>
                </tr>
            </tbody>
        </table>
        <p class="info-note">
            Saldo dihitung dari {{ $k['migrasi'] ? 'saldo awal migrasi per '.$k['tanggal_awal']?->translatedFormat('d M Y') : 'pencairan' }}
            (pokok {{ $rp($k['pokok_awal']) }}, jasa {{ $rp($k['jasa_awal']) }}) dikurangi angsuran yang tidak dibatalkan, urut tanggal transaksi.
        </p>

        @forelse ($k['masalah'] as $m)
            <p class="warn-note" style="font-size:12.5px;">⚠ {{ $m }}</p>
        @empty
            <p class="ok-note">✓ Saldo, jadwal dan status pinjaman ini sudah sesuai buku besar.</p>
        @endforelse

        @if ($j && $j['perlu'])
            <form method="POST" action="{{ route('admin.pinjaman.perbaikan-saldo.selaraskan', $loan) }}" style="margin-top:12px;"
                  onsubmit="return confirm('Selaraskan jadwal & status pinjaman ini dengan buku besar? Jurnal tidak disentuh, bisa dibatalkan dari menu Penyelarasan Jadwal.');">
                @csrf
                <button type="submit" class="btn-save">Selaraskan Jadwal &amp; Status dengan Buku Besar</button>
                <span class="tenang" style="margin-left:8px;">
                    Status {{ ucfirst($j['status_lama']) }} → {{ ucfirst($j['status_baru']) }}; sisa jadwal pokok {{ $rp($j['sisa_pokok_jadwal']) }} → {{ $rp($j['target_sisa_pokok']) }},
                    jasa {{ $rp($j['sisa_jasa_jadwal']) }} → {{ $rp($j['target_sisa_jasa']) }}.
                    <a class="tautan-kecil" href="{{ route('admin.pinjaman.penyelarasan-jadwal.show', $loan) }}">Rincian per baris</a>
                </span>
            </form>
        @endif
    </div>

    <div class="panel" style="overflow-x:auto;">
        <h3 style="margin-top:0;">Riwayat angsuran (urut tanggal transaksi)</h3>
        <p class="tenang" style="margin-top:-6px;">
            Baris abu-abu dicoret = dibatalkan (tidak mengurangi saldo). Klik <strong>Koreksi</strong> pada angsuran yang tanggal
            atau pecahan pokok/jasa/dendanya salah.
        </p>
        @include('admin.pinjaman.partials.perbaikan-saldo-tabel', ['k' => $k, 'aksi' => true, 'pengguna' => $pengguna])
    </div>
@endsection

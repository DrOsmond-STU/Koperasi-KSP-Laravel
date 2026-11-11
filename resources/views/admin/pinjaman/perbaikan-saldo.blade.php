@extends('layouts.app')

@section('title', 'Perbaikan Saldo Pinjaman Anggota')

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    <h2>Perbaikan Saldo Pinjaman Anggota</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 820px;">
        Saldo pinjaman dihitung dari <em>buku besar</em>: saldo awal (atau plafon pencairan) dikurangi pokok & jasa
        setiap angsuran yang tidak dibatalkan, <strong>diurutkan menurut tanggal transaksi</strong>. Buka kartu pinjaman
        untuk melihat saldo per tanggal, lalu perbaiki angsuran yang tanggal atau pecahan pokok/jasa/dendanya salah —
        sistem membatalkan angsuran itu (jurnalnya dibalik), mencatat penggantinya dengan jurnal yang benar, lalu
        menyelaraskan jadwal & status lunas. Tidak ada data yang dihapus.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.pinjaman.perbaikan-saldo.index') }}" class="panel" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <label for="q" style="font-size:13px; font-weight:600;">Cari pinjaman</label>
        <input type="text" id="q" name="q" value="{{ $cari }}" placeholder="No. pinjaman, nama, atau no. anggota" style="min-width:280px; padding:8px 10px;">
        <button type="submit" class="btn-save">Cari</button>
        @if ($cari !== '')
            <a class="tautan-kecil" href="{{ route('admin.pinjaman.perbaikan-saldo.index') }}">Tampilkan pinjaman bermasalah</a>
        @endif
    </form>

    <div class="panel">
        @if ($cari === '')
            <h3 style="margin-top:0;">Pinjaman yang perlu diperiksa ({{ $daftar->count() }})</h3>
            <p class="tenang" style="margin-top:-6px;">
                Sisa pokok/jasa minus, berstatus lunas padahal masih bersisa, jadwal tidak selaras dengan buku besar,
                angsuran bertanggal sebelum saldo awal/pencairan, atau angsuran yang diinput lebih dari
                {{ \App\Services\Loans\LoanBalanceCorrectionService::HARI_INPUT_TERLAMBAT }} hari setelah tanggalnya.
            </p>
        @else
            <h3 style="margin-top:0;">Hasil pencarian "{{ $cari }}" ({{ $daftar->count() }})</h3>
        @endif

        @if ($daftar->isEmpty())
            <p class="ok-note">{{ $cari === '' ? '✓ Tidak ada pinjaman yang terindikasi bermasalah.' : 'Tidak ada pinjaman yang cocok.' }}</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. Pinjaman</th>
                        <th>Anggota</th>
                        <th>Status</th>
                        <th class="num">Sisa Pokok<br><span class="tenang">buku besar</span></th>
                        <th class="num">Sisa Jasa<br><span class="tenang">buku besar</span></th>
                        <th>Yang perlu dicek</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $d)
                        @php $loan = $d['loan']; @endphp
                        <tr>
                            <td>{{ $loan->loan_number }}</td>
                            <td>{{ $loan->member->name ?? '-' }}<br><span class="tenang">{{ $loan->member->member_number ?? '' }}</span></td>
                            <td><span class="badge {{ $loan->status === 'lunas' ? 'badge-lunas' : 'badge-aktif' }}">{{ ucfirst($loan->status) }}</span></td>
                            <td class="num {{ $d['sisa_pokok'] < -0.5 ? 'selisih-plus' : '' }}">Rp {{ number_format($d['sisa_pokok'], 0, ',', '.') }}</td>
                            <td class="num {{ $d['sisa_jasa'] < -0.5 ? 'selisih-plus' : '' }}">Rp {{ number_format($d['sisa_jasa'], 0, ',', '.') }}</td>
                            <td>
                                @forelse ($d['masalah'] as $m)
                                    <span class="warn-note" style="display:block;">⚠ {{ $m }}</span>
                                @empty
                                    <span class="tenang">—</span>
                                @endforelse
                            </td>
                            <td><a class="tautan-kecil" href="{{ route('admin.pinjaman.perbaikan-saldo.show', $loan) }}">Buka kartu →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

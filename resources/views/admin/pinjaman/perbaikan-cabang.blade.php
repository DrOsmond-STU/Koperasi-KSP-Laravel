@extends('layouts.app')

@section('title', 'Perbaikan Cabang Pinjaman')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { padding: 9px 16px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .kartu { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; margin-bottom: 20px; }
        .angka-besar { font-size: 26px; font-weight: 700; color: var(--pine); }
        .bilah { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 18px; }
        .bilah label { display: flex; flex-direction: column; gap: 3px; font-size: 11px; color: var(--muted); font-weight: 600; }
        .bilah select { padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .konfirmasi { display: flex; gap: 8px; align-items: flex-start; margin: 14px 0; font-size: 13px; max-width: 620px; }
        .tenang { color: var(--muted); font-size: 12.5px; }
    </style>

    <h2>Perbaikan Cabang Pinjaman</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 700px;">
        Pinjaman dari transaksi POS "hutang" mewarisi cabang penjualannya, bukan cabang unit
        simpan pinjam. Akibatnya angsuran dan jurnalnya tercatat di cabang lain, dan pendapatan
        jasanya tidak masuk laba rugi unit yang sebenarnya menjalankan pinjaman itu.
        Layar ini memindahkannya. Bisa dijalankan ulang kapan saja, dan bisa dibatalkan.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p style="color:var(--brick); font-size:13px; margin-bottom:14px;">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <p style="color:var(--brick); font-size:13px; margin-bottom:14px;">{{ $errors->first() }}</p>
    @endif

    <form method="GET" action="{{ route('admin.pinjaman.perbaikan-cabang.form') }}" class="bilah">
        <label>
            Cabang tujuan
            <select name="branch_id" onchange="this.form.submit()">
                @foreach ($daftarCabang as $c)
                    <option value="{{ $c->id }}" @selected((int) $terpilih === $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                @endforeach
            </select>
        </label>
        <noscript><button type="submit" class="btn-primary">Lihat</button></noscript>
    </form>

    @if ($pratinjau === null)
        <p>Belum ada cabang aktif yang bisa dipilih.</p>
    @elseif ($pratinjau['total'] === 0)
        <div class="kartu">
            <p style="margin:0; color: var(--ok); font-weight: 700;">Sudah rapi.</p>
            <p class="tenang" style="margin: 6px 0 0;">
                Seluruh angsuran dan jurnalnya sudah tercatat di cabang ini. Tidak ada yang perlu dipindahkan.
            </p>
        </div>
    @else
        <div class="kartu">
            <p style="margin:0 0 4px; font-size:12px; color:var(--muted); font-weight:700;">AKAN DIPINDAHKAN</p>
            <p class="angka-besar" style="margin:0;">{{ number_format($pratinjau['total'], 0, ',', '.') }} baris</p>

            <table class="data-table" style="margin: 14px 0 0;">
                <tbody>
                    <tr><td style="width:220px;">Pinjaman (dari POS)</td><td>{{ number_format($pratinjau['loans'], 0, ',', '.') }}</td></tr>
                    <tr><td>Angsuran</td><td>{{ number_format($pratinjau['repayments'], 0, ',', '.') }}</td></tr>
                    <tr><td>Jurnal angsuran</td><td>{{ number_format($pratinjau['entries'], 0, ',', '.') }}</td></tr>
                </tbody>
            </table>

            <p style="margin:16px 0 6px; font-size:12px; color:var(--muted); font-weight:700;">SEKARANG TERCATAT DI</p>
            <table class="data-table" style="margin:0;">
                <thead><tr><th>Cabang</th><th>Jumlah baris</th></tr></thead>
                <tbody>
                    @foreach ($pratinjau['asal'] as $a)
                        <tr><td>{{ $a['cabang'] }}</td><td>{{ number_format($a['jumlah'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            @if ($pratinjau['pendapatan']->isNotEmpty())
                {{-- Inilah alasan sebenarnya layar ini ada. Jumlah baris cuma
                     ukuran pekerjaannya; angka inilah yang hilang dari laba rugi. --}}
                <p style="margin:16px 0 6px; font-size:12px; color:var(--muted); font-weight:700;">
                    PENDAPATAN YANG BELUM MASUK LABA RUGI CABANG INI
                </p>
                <table class="data-table" style="margin:0;">
                    <thead><tr><th>Akun</th><th>Nama</th><th style="text-align:right;">Jumlah</th></tr></thead>
                    <tbody>
                        @foreach ($pratinjau['pendapatan'] as $p)
                            <tr>
                                <td>{{ $p->code }}</td>
                                <td>{{ $p->name }}</td>
                                <td style="text-align:right;">Rp {{ number_format((float) $p->neto, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <form method="POST" action="{{ route('admin.pinjaman.perbaikan-cabang.apply') }}">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $terpilih }}">

                <div class="konfirmasi">
                    <input type="checkbox" name="konfirmasi" value="1" id="konfirmasi" required>
                    <label for="konfirmasi" style="font-weight:400;">
                        Saya sudah membaca angka di atas dan menyetujui pemindahannya. Yang berubah hanya
                        kolom cabang — nominal, akun, dan tanggal jurnal tidak disentuh, dan sistem akan
                        membatalkan sendiri kalau total debet/kredit sampai bergeser.
                    </label>
                </div>

                <button type="submit" class="btn-primary">Pindahkan ke cabang ini</button>
            </form>
        </div>
    @endif

    <h3>Riwayat Perbaikan</h3>
    <table class="data-table">
        <thead>
            <tr><th>Waktu</th><th>Cabang tujuan</th><th>Baris</th><th>Oleh</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($riwayat as $r)
                <tr>
                    <td>{{ $r->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>{{ $r->targetBranch->name ?? '-' }}</td>
                    <td>
                        {{ number_format($r->totalMoved(), 0, ',', '.') }}
                        <span class="tenang">({{ $r->loans_moved }} pinjaman, {{ $r->repayments_moved }} angsuran, {{ $r->entries_moved }} jurnal)</span>
                    </td>
                    <td>{{ $r->performedBy->name ?? '-' }}</td>
                    <td>
                        @if ($r->isReverted())
                            <span style="color:var(--brick);">Dibatalkan {{ $r->reverted_at->translatedFormat('d M Y H:i') }}</span>
                        @else
                            Berlaku
                        @endif
                    </td>
                    <td>
                        @unless ($r->isReverted())
                            <form method="POST" action="{{ route('admin.pinjaman.perbaikan-cabang.undo', $r) }}"
                                onsubmit="return confirm('Kembalikan {{ $r->totalMoved() }} baris ke cabang asalnya masing-masing?');">
                                @csrf
                                <button type="submit" class="btn-danger">Batalkan</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum pernah ada perbaikan cabang.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

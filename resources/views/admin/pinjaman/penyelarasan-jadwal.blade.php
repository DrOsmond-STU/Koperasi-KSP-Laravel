@extends('layouts.app')

@section('title', 'Penyelarasan Jadwal Angsuran')

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    <h2>Penyelarasan Jadwal Angsuran</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 760px;">
        Membandingkan sisa tagihan di <em>jadwal angsuran</em> dengan sisa menurut <em>buku besar</em>
        (pokok awal dikurangi porsi pokok tiap angsuran; jasa dihitung sama). Buku besar adalah sumber
        kebenarannya — jadwal disesuaikan mengikutinya: baris jadwal (pokok/jasa per angsuran) tidak diubah,
        hanya kolom <em>terbayar</em> dan status yang dihitung ulang dari angsuran tertua. Jurnal tidak disentuh,
        dan setiap penyelarasan bisa dibatalkan dari riwayat di bawah.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <p class="error-msg">{{ $errors->first() }}</p>
    @endif

    <div class="panel">
        @if ($rencana->isEmpty())
            <p class="ok-note">✓ Semua jadwal angsuran sudah selaras dengan buku besar.</p>
        @else
            <p class="error-msg" style="margin-bottom: 16px;">
                {{ $rencana->count() }} pinjaman jadwalnya tidak selaras dengan buku besar
                (selisih pokok Rp {{ number_format($rencana->sum(fn ($r) => abs($r['selisih_pokok'])), 0, ',', '.') }}).
            </p>

            <form method="POST" action="{{ route('admin.pinjaman.penyelarasan-jadwal.store') }}">
                @csrf
                <p class="select-all-row">
                    <label><input type="checkbox" id="select-all"> Pilih semua yang bisa diselaraskan</label>
                </p>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>No. Pinjaman</th>
                            <th>Anggota</th>
                            <th>Status</th>
                            <th class="num">Pokok Awal</th>
                            <th class="num">Pokok Dibayar</th>
                            <th class="num">Sisa Pokok<br><span class="tenang">buku besar</span></th>
                            <th class="num">Sisa Pokok<br><span class="tenang">jadwal</span></th>
                            <th class="num">Selisih Pokok</th>
                            <th class="num">Sisa Jasa<br><span class="tenang">buku / jadwal</span></th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rencana as $r)
                            @php $loan = $r['loan']; @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" name="loan_ids[]" value="{{ $loan->id }}" class="loan-checkbox" @disabled(! $r['bisa'])>
                                </td>
                                <td>{{ $loan->loan_number }}<br><span class="tenang">{{ $r['migrasi'] ? 'migrasi' : 'aplikasi' }}</span></td>
                                <td>{{ $loan->member->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $loan->status === 'lunas' ? 'badge-lunas' : 'badge-aktif' }}">{{ ucfirst($loan->status) }}</span>
                                    @if ($r['status_baru'] !== $loan->status)
                                        <br><span class="tenang">→ {{ ucfirst($r['status_baru']) }}</span>
                                    @endif
                                </td>
                                <td class="num">Rp {{ number_format($r['pokok_awal'], 0, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($r['pokok_bayar'], 0, ',', '.') }}</td>
                                <td class="num"><strong>Rp {{ number_format($r['target_sisa_pokok'], 0, ',', '.') }}</strong></td>
                                <td class="num">Rp {{ number_format($r['sisa_pokok_jadwal'], 0, ',', '.') }}</td>
                                <td class="num {{ $r['selisih_pokok'] > 0 ? 'selisih-plus' : 'selisih-minus' }}">
                                    {{ $r['selisih_pokok'] > 0 ? '+' : '' }}{{ number_format($r['selisih_pokok'], 0, ',', '.') }}
                                </td>
                                <td class="num">
                                    {{ $r['sisa_jasa_buku'] === null ? '—' : 'Rp '.number_format($r['sisa_jasa_buku'], 0, ',', '.') }}
                                    / Rp {{ number_format($r['sisa_jasa_jadwal'], 0, ',', '.') }}
                                </td>
                                <td>
                                    <a class="tautan-kecil" href="{{ route('admin.pinjaman.penyelarasan-jadwal.show', $loan) }}">Lihat rincian sebelum → sesudah</a>
                                    <br><span class="info-note">{{ $r['baris_berubah'] }} baris jadwal berubah</span>
                                    @foreach ($r['peringatan'] as $p)
                                        <p class="warn-note">⚠ {{ $p }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="konfirmasi">
                    <input type="checkbox" name="konfirmasi" value="1" id="konfirmasi" required>
                    <label for="konfirmasi" style="font-weight:400;">
                        Saya sudah membaca angka di atas dan menyetujui penyelarasannya. Yang berubah hanya kolom
                        terbayar/status pada jadwal dan status pinjaman — jurnal tidak disentuh, dan sistem
                        membatalkan sendiri kalau pembukuan sampai bergeser. Bisa dibatalkan dari riwayat.
                    </label>
                </div>

                <button type="submit" class="btn-save"
                        onclick="return confirm('Selaraskan jadwal untuk pinjaman yang dicentang dengan buku besar?');">
                    Selaraskan yang Dicentang
                </button>
            </form>
        @endif
    </div>

    <h3>Riwayat Penyelarasan</h3>
    <table class="data-table">
        <thead>
            <tr><th>Waktu</th><th>Pinjaman</th><th class="num">Baris jadwal</th><th>Oleh</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($riwayat as $a)
                <tr>
                    <td>{{ $a->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>
                        {{ $a->loans_aligned }} pinjaman
                        <span class="tenang">({{ implode(', ', array_slice($a->nomorPinjaman(), 0, 8)) }}{{ count($a->nomorPinjaman()) > 8 ? ', …' : '' }})</span>
                    </td>
                    <td class="num">{{ number_format($a->rows_changed, 0, ',', '.') }}</td>
                    <td>{{ $a->performedBy->name ?? '-' }}</td>
                    <td>
                        @if ($a->isReverted())
                            <span style="color:var(--brick);">Dibatalkan {{ $a->reverted_at->translatedFormat('d M Y H:i') }}</span>
                        @else
                            Berlaku
                        @endif
                    </td>
                    <td>
                        <a class="tautan-kecil" href="{{ route('admin.pinjaman.penyelarasan-jadwal.riwayat', $a) }}">Rincian</a>
                        @unless ($a->isReverted())
                            <form method="POST" action="{{ route('admin.pinjaman.penyelarasan-jadwal.undo', $a) }}" style="display:inline; margin-left:8px;"
                                  onsubmit="return confirm('Kembalikan jadwal {{ $a->loans_aligned }} pinjaman ini persis ke sebelum penyelarasan?');">
                                @csrf
                                <button type="submit" class="btn-danger">Batalkan</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum pernah ada penyelarasan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>
        var selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.loan-checkbox:not([disabled])').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
            });
        }
    </script>
@endsection

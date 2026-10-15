@extends('layouts.app')

@section('title', 'Antrian Persetujuan Pinjaman')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .approve-form { display: flex; align-items: flex-end; gap: 6px; margin-bottom: 6px; }
        .approve-form label { display: flex; flex-direction: column; gap: 2px; font-size: 11px; color: var(--muted); }
        .approve-form input[type="date"] { padding: 5px 8px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; }
        .catatan-putusan { font-size: 11.5px; color: var(--muted); margin: 0 0 6px; max-width: 320px; }
        .bilah-saring { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 16px; }
        .bilah-saring label { display: flex; flex-direction: column; gap: 3px; font-size: 11px; color: var(--muted); font-weight: 600; }
        {{-- Warna latar/teks sengaja tidak diatur di sini: layouts.app sudah
             menatanya global untuk input/select, dan menimpanya dengan nilai
             terang membuat kotak ini putih menyala di tema gelap. --}}
        .bilah-saring input[type="search"], .bilah-saring select { padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .bilah-saring input[type="search"] { min-width: 260px; }
        .btn-secondary { padding: 8px 14px; background: var(--paper); color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
        .hasil-saring { font-size: 12px; color: var(--muted); margin: -6px 0 10px; }
        .penavigasi { display: flex; align-items: center; gap: 12px; margin: -10px 0 24px; flex-wrap: wrap; }
        .penavigasi .nonaktif { opacity: .45; cursor: default; }
    </style>

    <h2>Antrian Persetujuan Pinjaman</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 680px;">
        Tanggal pencairan adalah tanggal uang benar-benar keluar — dipakai untuk jurnal kas,
        tanggal cair, dan awal jadwal angsuran. Untuk akad lama yang baru dicatat sekarang,
        isikan tanggal akad yang sebenarnya, bukan hari ini.
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

    {{-- Satu bilah untuk kedua tabel: pencari satu nomor pinjaman belum tentu
         tahu pinjaman itu masih menunggu atau sudah cair. Filter Status hanya
         mengenai tabel bawah, karena tabel atas menurut definisinya berisi
         satu status saja. --}}
    <form method="GET" action="{{ route('admin.pinjaman.index') }}" class="bilah-saring">
        <label>
            Cari
            <input type="search" name="cari" value="{{ $cari }}"
                placeholder="No. pinjaman, nama, atau no. anggota...">
        </label>
        <label>
            Produk
            <select name="produk">
                <option value="">Semua produk</option>
                @foreach ($daftarProduk as $p)
                    <option value="{{ $p->id }}" @selected($produk === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </label>
        @if ($daftarCabang->count() > 1)
            <label>
                Cabang
                <select name="cabang">
                    <option value="">Semua cabang</option>
                    @foreach ($daftarCabang as $c)
                        <option value="{{ $c->id }}" @selected($cabang === $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label>
            Status (pinjaman cair)
            <select name="status">
                <option value="">Semua</option>
                <option value="dicairkan" @selected($status === 'dicairkan')>Dicairkan</option>
                <option value="dibatalkan" @selected($status === 'dibatalkan')>Dibatalkan</option>
            </select>
        </label>
        <button type="submit" class="btn-primary" style="padding:9px 16px;">Cari</button>
        @if ($cari !== '' || $produk || $cabang || $status !== '')
            <a href="{{ route('admin.pinjaman.index') }}" class="btn-secondary">Bersihkan</a>
        @endif
    </form>

    <table class="data-table">
        <thead>
            <tr><th>No. Pinjaman</th><th>Anggota</th><th>Produk</th><th>Plafon</th><th>Approval</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($pendingLoans as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>{{ $loan->loanProduct->name }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td>{{ $loan->approvalCount() }}/{{ $loan->required_approval_count }}</td>
                    @php
                        $pembuat = $loan->created_by === auth()->id();
                        $sudahMemutus = $loan->approvals->contains('approved_by', auth()->id());
                        $bolehMemutus = ! $pembuat && ! $sudahMemutus;
                        $belumMemutus = $approverNames
                            ->except($loan->approvals->pluck('approved_by')->push($loan->created_by)->all());
                    @endphp
                    <td>
                        @if ($bolehMemutus)
                            <form method="POST" action="{{ route('admin.pinjaman.decide', $loan) }}" class="approve-form">
                                @csrf
                                <input type="hidden" name="decision" value="setuju">
                                <label>
                                    Tgl. pencairan
                                    {{-- Bawaannya tanggal pengajuan, bukan hari ini. Untuk akad
                                         lama yang dicatat susulan, hari ini hampir selalu salah:
                                         pinjaman 51-100H-260813-9703 batal 16 Sep 2026 justru
                                         karena tanggalnya terlewat dibiarkan di hari itu. Untuk
                                         pengajuan hari ini keduanya bernilai sama. --}}
                                    <input type="date" name="disbursed_on" required
                                        value="{{ old('disbursed_on', ($loan->submitted_at ?? now())->toDateString()) }}"
                                        min="{{ $loan->submitted_at?->toDateString() }}"
                                        max="{{ now()->toDateString() }}">
                                </label>
                                <button type="submit" class="btn-primary">Setujui</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pinjaman.decide', $loan) }}" style="display:inline;">
                                @csrf
                                <input type="hidden" name="decision" value="tolak">
                                <input type="hidden" name="notes" value="Ditolak oleh pengurus">
                                <button type="submit" class="btn-danger">Tolak</button>
                            </form>
                        @else
                            <p class="catatan-putusan">
                                {{ $pembuat ? 'Anda pembuat pengajuan ini.' : 'Anda sudah memberi keputusan.' }}
                                @if ($belumMemutus->isNotEmpty())
                                    Menunggu: {{ $belumMemutus->take(4)->implode(', ') }}{{ $belumMemutus->count() > 4 ? ', dan '.($belumMemutus->count() - 4).' lainnya' : '' }}.
                                @else
                                    <strong>Tidak ada lagi yang berhak memutus</strong> — batalkan pengajuannya.
                                @endif
                            </p>
                        @endif

                        @if ($loan->canBeCancelledBy(auth()->user()))
                            <button type="button" class="btn-danger" data-toggle-cancel="ajuan-{{ $loan->id }}">Batalkan Pengajuan</button>
                            <form method="POST" action="{{ route('admin.pinjaman.batalkan-pengajuan', $loan) }}" class="cancel-form" id="cancel-form-ajuan-{{ $loan->id }}" style="display:none; gap:6px; margin-top:6px;">
                                @csrf
                                <input type="text" name="reason" placeholder="Alasan" required style="width:140px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; font-size:11px;">
                                <button type="submit" class="btn-danger">OK</button>
                            </form>
                        @endif

                        <a href="{{ route('admin.print.loan-application.show', $loan) }}" class="btn-link" target="_blank">Cetak</a>
                    </td>
                </tr>
            @empty
                {{-- Dibedakan dengan sengaja: "tidak ada yang cocok" dan "antrian
                     memang kosong" adalah dua kabar yang sangat berbeda bagi staf
                     yang sedang mencari satu pengajuan. --}}
                <tr><td colspan="6">
                    @if ($cari !== '' || $produk || $cabang)
                        Tidak ada pengajuan menunggu persetujuan yang cocok dengan saringan ini.
                    @else
                        Tidak ada pengajuan menunggu persetujuan.
                    @endif
                </td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>{{ $cari !== '' || $produk || $cabang || $status !== '' ? 'Pinjaman Dicairkan — Hasil Pencarian' : 'Pinjaman Dicairkan Terbaru' }}</h3>
    @if ($disbursedLoans->total() > 0)
        <p class="hasil-saring">
            Menampilkan {{ $disbursedLoans->firstItem() }}–{{ $disbursedLoans->lastItem() }}
            dari {{ number_format($disbursedLoans->total(), 0, ',', '.') }} pinjaman.
        </p>
    @endif
    <table class="data-table">
        <thead><tr><th>No. Pinjaman</th><th>Anggota</th><th>Plafon</th><th>Tanggal Cair</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($disbursedLoans as $loan)
                <tr>
                    <td>{{ $loan->loan_number }}</td>
                    <td>{{ $loan->member->name }}</td>
                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                    <td>{{ $loan->disbursed_at?->translatedFormat('d M Y') }}</td>
                    <td>
                        @if ($loan->isCancelled())
                            <span style="color:var(--brick);">Dibatalkan</span>
                        @else
                            Dicairkan
                        @endif
                    </td>
                    <td>
                        @if (! $loan->isCancelled() && $loan->canBeCancelledBy(auth()->user()))
                            <button type="button" class="btn-danger" data-toggle-cancel="loan-{{ $loan->id }}">Batalkan</button>
                            <form method="POST" action="{{ route('admin.pinjaman.cancel', $loan) }}" class="cancel-form" id="cancel-form-loan-{{ $loan->id }}" style="display:none; gap:6px; margin-top:6px;">
                                @csrf
                                <input type="text" name="reason" placeholder="Alasan" required style="width:110px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; font-size:11px;">
                                <button type="submit" class="btn-danger">OK</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.print.loan-application.show', $loan) }}" class="btn-link" target="_blank">Cetak Pengajuan</a>
                        <a href="{{ route('admin.print.loans.schedule', $loan) }}" class="btn-link" target="_blank">Cetak Angsuran</a>
                        @can('pinjaman.create')
                            @unless ($loan->isCancelled())
                                <a href="{{ route('staf.angsuran.create', ['loan_id' => $loan->id]) }}" class="btn-link">Catat Angsuran</a>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">
                    @if ($cari !== '' || $produk || $cabang || $status !== '')
                        Tidak ada pinjaman cair yang cocok dengan saringan ini.
                    @else
                        Belum ada pinjaman dicairkan.
                    @endif
                </td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Penavigasi ditulis tangan, bukan $paginator->links(): bawaan Laravel
         adalah markup Tailwind, sedangkan aplikasi ini memakai CSS sendiri —
         hasilnya gumpalan tanpa gaya. withQueryString() di controller yang
         membuat saringan aktif ikut terbawa antar halaman. --}}
    @if ($disbursedLoans->hasPages())
        <div class="penavigasi">
            @if ($disbursedLoans->previousPageUrl())
                <a href="{{ $disbursedLoans->previousPageUrl() }}" class="btn-secondary">‹ Sebelumnya</a>
            @else
                <span class="btn-secondary nonaktif">‹ Sebelumnya</span>
            @endif

            <span class="hasil-saring" style="margin:0;">
                Halaman {{ $disbursedLoans->currentPage() }} dari {{ $disbursedLoans->lastPage() }}
            </span>

            @if ($disbursedLoans->nextPageUrl())
                <a href="{{ $disbursedLoans->nextPageUrl() }}" class="btn-secondary">Berikutnya ›</a>
            @else
                <span class="btn-secondary nonaktif">Berikutnya ›</span>
            @endif
        </div>
    @endif

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            });
        });
    </script>
@endsection

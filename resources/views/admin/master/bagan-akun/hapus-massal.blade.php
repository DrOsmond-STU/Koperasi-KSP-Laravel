@extends('layouts.app')

@section('title', 'Hapus Massal Bagan Akun')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .data-table tr.locked td { color: var(--muted); background: rgba(0,0,0,.015); }
        .btn-primary { padding: 9px 16px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { padding: 10px 18px; background: var(--brick); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; font-size: 13px; line-height: 1.6; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-bar label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 5px; }
        .filter-bar select, .filter-bar input { padding: 8px 11px; border: 1px solid var(--line); border-radius: 9px; }
        .confirm-bar { position: sticky; bottom: 0; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-top: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .confirm-bar input[type=text] { padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; width: 130px; }
        .err-box { background: rgba(0,0,0,.02); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; max-height: 300px; overflow: auto; }
        .err-box li { font-size: 13px; margin-bottom: 5px; }
        .muted { color: var(--muted); }
        .reason { font-size: 12px; color: var(--brick); }
        .table-wrap { max-height: 560px; overflow: auto; border-radius: 12px; }
    </style>

    <h2>Hapus Massal Bagan Akun</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.master.chart-of-accounts.index') }}" class="btn-link">&larr; Kembali ke Bagan Akun</a>
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-msg">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (session('purge_gagal'))
        <div class="err-box">
            <strong>Tidak bisa dihapus ({{ count(session('purge_gagal')) }}):</strong>
            <ul>
                @foreach (session('purge_gagal') as $gagal)
                    <li>{{ $gagal['code'] }} — {{ $gagal['name'] }}: {{ $gagal['alasan'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="notice">
        Penghapusan <strong>tidak bisa dibatalkan</strong>. Akun yang masih dipakai jurnal, Saldo Awal, produk
        simpanan/pinjaman, atau master data lain <strong>dikunci otomatis</strong> dan tidak bisa dicentang —
        alasannya ditulis di kolom paling kanan. Enam akun inti
        ({{ implode(', ', \App\Models\ChartOfAccount::PROTECTED_CODES) }}) juga selalu terkunci.
        <br>
        Untuk membuang akun yang masih terpakai, lepaskan dulu pemakaiannya (mis. arahkan produk ke akun lain).
        Akun induk baru bisa dihapus bila seluruh akun anaknya ikut dicentang.
    </div>

    <form method="GET" action="{{ route('admin.master.chart-of-accounts.purge.form') }}" class="filter-bar">
        <div>
            <label>Sumber akun</label>
            <select name="sumber">
                <option value="semua" @selected($sumber === 'semua')>Semua akun</option>
                <option value="migrasi" @selected($sumber === 'migrasi')>Hasil import migrasi SMIK</option>
                <option value="bawaan" @selected($sumber === 'bawaan')>Bukan hasil import (bawaan sistem)</option>
            </select>
        </div>
        <div>
            <label>Awalan kode</label>
            <input type="text" name="awalan" value="{{ $awalan }}" placeholder="mis. 11">
        </div>
        <button type="submit" class="btn-primary">Tampilkan</button>
        <span class="muted" style="font-size:13px;">
            {{ $accounts->count() }} dari {{ $jumlahTotal }} akun ·
            <strong>{{ $accounts->count() - $jumlahTerkunci }} bisa dihapus</strong> ·
            {{ $jumlahTerkunci }} terkunci
        </span>
    </form>

    @if ($accounts->count() > 0 && $accounts->count() === $jumlahTerkunci)
        <div class="notice" style="border-color: var(--brick);">
            Semua akun pada tampilan ini <strong>terkunci</strong>, jadi tidak ada yang bisa dicentang.
            Lepaskan dulu pemakaiannya (mis. arahkan produk simpanan/pinjaman ke akun lain), atau ubah filter di atas.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.master.chart-of-accounts.purge') }}"
          onsubmit="return confirm('Hapus akun yang dicentang? Tindakan ini tidak bisa dibatalkan.');">
        @csrf
        <input type="hidden" name="sumber" value="{{ $sumber }}">
        <input type="hidden" name="awalan" value="{{ $awalan }}">

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" id="pilih-semua" title="Pilih semua yang bisa dihapus"></th>
                        <th>Kode</th><th>Nama Akun</th><th>Tipe</th><th>Induk</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        @php
                            $alasan = $blockers[$account->id] ?? [];
                            $anak = $childCounts[$account->code] ?? 0;
                        @endphp
                        <tr class="{{ $alasan !== [] ? 'locked' : '' }}">
                            <td>
                                @if ($alasan === [])
                                    <input type="checkbox" name="ids[]" value="{{ $account->id }}" class="pilih">
                                @endif
                            </td>
                            <td>{{ $account->code }}</td>
                            <td>{{ $account->name }}</td>
                            <td class="muted">{{ $account->type }}</td>
                            <td class="muted">{{ $account->parent_code ?? '—' }}</td>
                            <td>
                                @if ($alasan !== [])
                                    <span class="reason">Terkunci: {{ implode(', ', $alasan) }}</span>
                                @elseif ($anak > 0)
                                    <span class="muted">Punya {{ $anak }} akun anak — centang anaknya juga</span>
                                @else
                                    <span class="muted">Bisa dihapus</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Tidak ada akun yang cocok dengan filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="confirm-bar">
            <span style="font-size:13px;">Ketik <strong>HAPUS</strong> (huruf besar) lalu tekan tombol:</span>
            <input type="text" name="konfirmasi" id="konfirmasi" autocomplete="off" placeholder="HAPUS">
            <button type="submit" class="btn-danger">Hapus Akun Terpilih</button>
            <span class="muted" id="hitungan" style="font-size:13px;"></span>
        </div>
    </form>

    {{--
        Tombol sengaja TIDAK pernah di-disable lewat JavaScript. Versi
        sebelumnya menonaktifkannya sampai kotak konfirmasi terisi, dan itu
        membuat satu kesalahan kecil di skrip ini — atau satu elemen yang tidak
        ketemu — muncul sebagai tombol mati tanpa penjelasan apa pun.
        PurgeChartOfAccountRequest sudah memvalidasi kata kunci konfirmasi dan
        keharusan memilih baris di sisi server, lengkap dengan pesan berbahasa
        Indonesia, jadi menekan tombol selalu memberi jawaban yang bisa dibaca.
        Skrip di bawah murni penghitung, dan setiap elemen dicek dulu supaya
        tidak ada yang bisa menggagalkan pengiriman form.
    --}}
    <script>
        (function () {
            var kotak = Array.prototype.slice.call(document.querySelectorAll('.pilih'));
            var semua = document.getElementById('pilih-semua');
            var hitungan = document.getElementById('hitungan');

            function segarkan() {
                if (!hitungan) { return; }
                var n = kotak.filter(function (k) { return k.checked; }).length;
                hitungan.textContent = n + ' dari ' + kotak.length + ' akun dicentang';
            }

            if (semua) {
                semua.addEventListener('change', function () {
                    kotak.forEach(function (k) { k.checked = semua.checked; });
                    segarkan();
                });
            }
            kotak.forEach(function (k) { k.addEventListener('change', segarkan); });
            segarkan();
        })();
    </script>
@endsection

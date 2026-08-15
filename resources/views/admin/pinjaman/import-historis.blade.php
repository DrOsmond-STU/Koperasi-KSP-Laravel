@extends('layouts.app')

@section('title', 'Import Riwayat Pembayaran')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 720px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.55; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; font-size: 13px; line-height: 1.6; margin-bottom: 18px; max-width: 720px; }
        .err-box { background: rgba(0,0,0,.02); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px; max-height: 340px; overflow: auto; max-width: 720px; }
        .err-box li { margin-bottom: 6px; font-size: 13px; }
        .angka { font-weight: 700; }
    </style>

    <h2>Import Riwayat Pembayaran</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.saldo-awal.index') }}" class="btn-link">&larr; Kembali ke Saldo Awal</a>
    </p>

    @if (session('error'))
        <div class="error-text">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="err-box">
            <strong>Baris yang gagal (menampilkan {{ count(session('import_errors')) }} pertama):</strong>
            <ul>
                @foreach (session('import_errors') as $failure)
                    <li>Baris {{ $failure['row'] }} — {{ implode('; ', $failure['errors']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="notice">
        Saat ini ada <span class="angka">{{ number_format($jumlahPinjaman, 0, ',', '.') }} pinjaman</span>,
        dengan <span class="angka">{{ number_format($jumlahRiwayat, 0, ',', '.') }} baris riwayat migrasi</span>
        dan <span class="angka">{{ number_format($jumlahBerjalan, 0, ',', '.') }} pembayaran berjalan</span>.
        <br><br>
        Baris yang masuk lewat layar ini adalah <strong>arsip, bukan transaksi</strong>. Saldo awal sudah
        memuat posisi akhir piutang pada tanggal cutoff — angka itu sudah memperhitungkan seluruh
        pembayaran di masa lalu. Karena itu import ini:
        <br>
        <strong>tidak membuat jurnal</strong> · <strong>tidak mengubah sisa pokok</strong> ·
        <strong>tidak mengubah jadwal angsuran</strong> · <strong>tidak mengubah status pinjaman</strong>.
        <br><br>
        Setiap baris ditandai sebagai hasil migrasi, sehingga selamanya bisa dibedakan dari pembayaran
        yang benar-benar terjadi di aplikasi ini. Hasilnya bisa diperiksa di
        <strong>Laporan → Migrasi &amp; Saldo Awal → Migrasi — Historis Pembayaran</strong>.
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.pinjaman.historis.import') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label>File CSV</label>
                <input type="file" name="file" accept=".csv,text/csv" required>
                <div class="hint">
                    Kolom: <code>no_pinjaman_lama, tanggal_bayar, nominal_pokok, nominal_jasa, saldo_setelah, keterangan</code>
                    <br>
                    <code>no_pinjaman_lama</code> dicocokkan ke Nomor Pinjaman ·
                    tanggal: <code>YYYY-MM-DD</code>, <code>DD/MM/YYYY</code>, atau <code>DD-MM-YYYY</code>.
                    <br>
                    Jumlah bayar dihitung sendiri dari <code>nominal_pokok + nominal_jasa</code>.
                    Maksimal 10 MB.
                    <br>
                    Berkas berisi puluhan ribu baris — prosesnya bisa memakan waktu satu sampai dua menit.
                    Jangan tutup atau muat ulang halaman selama diproses.
                </div>
            </div>

            <div class="field">
                <label>Mode</label>
                <select name="mode" required>
                    <option value="all_or_nothing">All-or-nothing — simpan hanya bila tidak ada error</option>
                    <option value="partial">Partial — simpan baris yang valid saja</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Validasi &amp; Import</button>
        </form>
    </div>
@endsection

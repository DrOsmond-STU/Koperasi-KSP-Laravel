@extends('layouts.app')

@section('title', 'Import Jadwal Angsuran')

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
        .perlu { color: var(--brick); }
    </style>

    <h2>Import Jadwal Angsuran</h2>
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
            <strong>Baris yang gagal ({{ count(session('import_errors')) }}):</strong>
            <ul>
                @foreach (session('import_errors') as $failure)
                    <li>Baris {{ $failure['row'] }} — {{ implode('; ', $failure['errors']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="notice">
        Saat ini ada <span class="angka">{{ number_format($jumlahPinjaman, 0, ',', '.') }} pinjaman</span>
        dengan <span class="angka">{{ number_format($jumlahJadwal, 0, ',', '.') }} baris jadwal</span>,
        dan <span class="angka {{ $tanpaJadwal > 0 ? 'perlu' : '' }}">{{ number_format($tanpaJadwal, 0, ',', '.') }} pinjaman belum punya jadwal sama sekali</span>.
        <br><br>
        Alat ini menambal jadwal yang luput saat migrasi — mis. batch saldo awal terlanjur dikunci
        sebelum Saldo Awal Angsuran sempat diimport, sehingga pinjaman berdiri tanpa jadwal dan
        batchnya tidak bisa diulang.
        <br><br>
        <strong>Tidak ada jurnal yang dibuat.</strong> Jadwal angsuran adalah rencana pembayaran, bukan
        peristiwa akuntansi — posisi piutangnya sudah dibukukan jurnal pembukaan saat batch dikunci.
        Baris yang nomor angsurannya sudah ada di jadwal <strong>ditolak, bukan ditimpa</strong>.
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.pinjaman.jadwal.import') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label>File CSV</label>
                <input type="file" name="file" accept=".csv,text/csv" required>
                <div class="hint">
                    Kolom: <code>no_pinjaman_lama, angsuran_ke, tanggal_jatuh_tempo, nominal_pokok, nominal_jasa, nominal_denda, status</code>
                    <br>
                    <code>no_pinjaman_lama</code> dicocokkan ke Nomor Pinjaman ·
                    <code>status</code>: Belum Bayar / Sebagian / Lunas ·
                    tanggal: <code>YYYY-MM-DD</code>, <code>DD/MM/YYYY</code>, atau <code>DD-MM-YYYY</code>.
                    <br>
                    <code>nominal_denda</code> <strong>diabaikan</strong> — jadwal tidak menyimpan denda; denda dihitung
                    saat pembayaran dari tarif per hari milik produk pinjaman.
                    <br>
                    Status <code>Sebagian</code> wajib disertai kolom tambahan <code>nominal_dibayar</code>,
                    karena tanpa angkanya sisa tagihan akan salah.
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

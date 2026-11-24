@extends('layouts.app')

@section('title', 'Pengajuan Pinjaman')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 480px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .field .hint-batas { font-size: 11px; color: var(--muted); margin-top: 4px; padding-left: 9px; border-left: 2px solid var(--line); }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .error-list { color: var(--brick); font-size: 13px; margin: 0 0 14px; padding-left: 18px; }
    </style>

    <h2>Pengajuan Pinjaman Baru</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <ul class="error-list">
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    @endif

    <div class="panel">
        <form method="POST" action="{{ route('staf.pengajuan-pinjaman.simulate') }}">
            @csrf
            <div class="field">
                <label>Anggota</label>
                <select name="member_id" required class="js-searchable">
                    <option value="">— Pilih Anggota —</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected(old('member_id') == $member->id)>{{ $member->member_number }} — {{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Produk Pinjaman</label>
                <select name="loan_product_id" required>
                    <option value="">— Pilih Produk —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('loan_product_id') == $product->id)>{{ $product->name }} — {{ $product->tenorLabel() }} (Rp {{ number_format($product->min_plafon, 0, ',', '.') }}–Rp {{ number_format($product->max_plafon, 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Nominal Pinjaman (Rp)</label>
                <input type="number" step="0.01" name="principal_amount" value="{{ old('principal_amount') }}" required>
            </div>
            <div class="field">
                <label>Tenor</label>
                <input type="number" name="tenor_days" value="{{ old('tenor_days') }}" required>
                <p class="hint">Isi sesuai satuan produk yang dipilih di atas (lihat keterangan hari/bulan).</p>
            </div>
            <div class="field">
                <label>Tanggal Pengajuan</label>
                <input type="date" name="submitted_at"
                       value="{{ old('submitted_at', now()->toDateString()) }}"
                       min="{{ $tanggalPalingAwal }}"
                       max="{{ now()->toDateString() }}" required>
                <p class="hint">
                    Biarkan hari ini untuk pengajuan biasa. Mundurkan tanggalnya bila sedang
                    mencatat pinjaman yang sudah cair tapi belum sempat masuk sistem — jadwal
                    angsuran akan dihitung mulai tanggal ini, bukan mulai hari ini.
                </p>
                <p class="hint-batas">
                    Paling mundur sampai {{ \Illuminate\Support\Carbon::parse($tanggalPalingAwal)->translatedFormat('d F Y') }}.
                    Pinjaman yang cair sampai dengan {{ $labelCutoff }} sudah terhitung di saldo awal,
                    jadi mencatatnya di sini akan membuatnya terhitung dua kali. Bila datanya keliru,
                    perbaiki lewat menu saldo awal.
                </p>
            </div>
            <button type="submit" class="btn-primary">Simulasikan Jadwal Angsuran</button>
        </form>
    </div>
@endsection

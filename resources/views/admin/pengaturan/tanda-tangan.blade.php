@extends('layouts.app')

@section('title', 'Setup Tanda Tangan')

@php
    $groupLabels = [
        'pengajuan_pinjaman' => 'Pengajuan Pinjaman',
        'pengajuan_penarikan' => 'Pengajuan Penarikan Simpanan',
        'kas_keluar' => 'Bukti Kas Keluar Teller',
        'kas_masuk' => 'Bukti Kas Masuk Teller',
        'jurnal_umum' => 'Jurnal Umum',
        'dokumen_gudang' => 'Pembelian / Retur / Koreksi Persediaan',
        'aktiva_tetap' => 'Pembelian / Pelepasan Aktiva Tetap',
        'laporan_kas_bank' => 'Laporan Harian Kas/Bank',
        'laporan_upf' => 'Laporan Harian Retribusi UPF',
        'unit_usaha' => 'Transaksi Unit Usaha',
    ];
@endphp

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .field { margin-bottom: 12px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; }
        .inline-form { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 14px; }
        .inline-form .field { margin-bottom: 0; min-width: 180px; flex: 1; }
        .btn-primary { padding: 8px 16px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .btn-danger { padding: 6px 12px; background: transparent; border: 1px solid var(--brick); color: var(--brick); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 12px; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .empty-note { color: var(--muted); font-size: 13px; }
        .group-title { font-size: 14px; font-weight: 800; margin: 0 0 10px; }
        .slot-row { display: flex; gap: 10px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
        .slot-row form { display: flex; gap: 8px; align-items: center; flex: 1; flex-wrap: wrap; }
        .slot-row input, .slot-row select { padding: 7px 9px; border: 1px solid var(--line); border-radius: 7px; font-size: 13px; }
        .link-alt { color: var(--pine-bright); font-size: 13px; text-decoration: none; }
    </style>

    <p><a href="{{ route('admin.pengaturan.cetakan.edit') }}" class="link-alt">&larr; Kembali ke Pengaturan Cetakan</a></p>
    <h2>Setup Tanda Tangan</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px;">
        Penanda tangan (nama+jabatan) tidak perlu punya akun login — ini hanya untuk kebutuhan cetak, tanda tangan tetap dilakukan di atas kertas.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="panel">
        <h3 class="group-title">Daftar Penanda Tangan</h3>

        <form method="POST" action="{{ route('admin.pengaturan.tanda-tangan.signatory.store') }}" class="inline-form">
            @csrf
            <div class="field">
                <label>Nama</label>
                <input type="text" name="name" required>
            </div>
            <div class="field">
                <label>Jabatan</label>
                <input type="text" name="title" placeholder="Ketua Koperasi" required>
            </div>
            <button type="submit" class="btn-primary">Tambah</button>
        </form>

        <table class="data-table">
            <thead><tr><th>Nama</th><th>Jabatan</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($signatories as $signatory)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('admin.pengaturan.tanda-tangan.signatory.update', $signatory) }}" style="display:flex; gap:6px;">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $signatory->name }}" style="max-width:160px;">
                        </td>
                        <td>
                                <input type="text" name="title" value="{{ $signatory->title }}" style="max-width:140px;">
                        </td>
                        <td>
                                <button type="submit" class="btn-primary">Simpan</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pengaturan.tanda-tangan.signatory.destroy', $signatory) }}" style="display:inline;" onsubmit="return confirm('Hapus penanda tangan ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3"><p class="empty-note">Belum ada penanda tangan.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach ($documentGroups as $group)
        <div class="panel">
            <h3 class="group-title">{{ $groupLabels[$group] ?? $group }}</h3>

            @foreach (($slotsByGroup[$group] ?? []) as $slot)
                <div class="slot-row">
                    <form method="POST" action="{{ route('admin.pengaturan.tanda-tangan.slot.update', $slot) }}">
                        @csrf
                        @method('PUT')
                        <input type="text" name="label" value="{{ $slot->label }}" style="min-width:220px;">
                        <select name="document_signatory_id">
                            <option value="">— Kosong (hanya garis) —</option>
                            @foreach ($signatories as $signatory)
                                <option value="{{ $signatory->id }}" @selected($slot->document_signatory_id === $signatory->id)>{{ $signatory->name }} — {{ $signatory->title }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary">Simpan</button>
                    </form>
                    <form method="POST" action="{{ route('admin.pengaturan.tanda-tangan.slot.destroy', $slot) }}" onsubmit="return confirm('Hapus slot ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                </div>
            @endforeach

            <form method="POST" action="{{ route('admin.pengaturan.tanda-tangan.slot.store') }}" class="inline-form" style="margin-top:10px;">
                @csrf
                <input type="hidden" name="document_group" value="{{ $group }}">
                <div class="field">
                    <label>Tambah Slot Baru</label>
                    <input type="text" name="label" placeholder="Label tanda tangan" required>
                </div>
                <button type="submit" class="btn-primary">Tambah Slot</button>
            </form>
        </div>
    @endforeach
@endsection

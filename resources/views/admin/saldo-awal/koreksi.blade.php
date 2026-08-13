@extends('layouts.app')

@section('title', 'Koreksi Data Saldo Awal')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .btn-danger { padding: 6px 12px; background: var(--brick); color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; }
        .btn-danger[disabled] { background: var(--line); color: var(--muted); cursor: not-allowed; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; font-size: 13px; line-height: 1.6; margin-bottom: 18px; }
        .chip { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .chip-draft { background: #F3E6CC; color: #9C6A1E; }
        .chip-locked { background: #E9F1EC; color: #11543B; }
    </style>

    <h2>Koreksi Data Saldo Awal</h2>
    <p style="color: var(--muted); margin-top: -8px;">
        {{ $batch->branch->name }} — cut-off {{ $batch->cutoff_date->translatedFormat('d M Y') }}
        <span class="chip {{ $batch->isDraft() ? 'chip-draft' : 'chip-locked' }}">{{ $batch->isDraft() ? 'Draft' : 'Terkunci' }}</span>
    </p>

    <p style="margin-top: -4px;">
        <a href="{{ route('admin.saldo-awal.show', $batch) }}" class="btn-link">&larr; Kembali ke batch</a>
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    @if ($batch->isDraft())
        <div class="notice">
            Mengosongkan sub-modul menghapus <strong>seluruh baris</strong> sub-modul itu pada batch ini, supaya bisa diimport ulang dari awal.
            Belum ada jurnal yang terbit selama batch masih Draft, jadi aman diulang.
            <br>
            Mengosongkan <strong>Pinjaman</strong> otomatis ikut mengosongkan <strong>Angsuran</strong>, karena baris angsuran menempel pada pinjaman.
        </div>
    @else
        <div class="notice">
            Batch ini <strong>sudah terkunci</strong> dan jurnal pembukaannya sudah terbit. Saldo awal tidak boleh diubah lagi —
            koreksi harus lewat <strong>Jurnal Penyesuaian</strong> agar jejak akuntansinya tetap utuh.
        </div>
    @endif

    <table class="data-table">
        <thead>
            <tr><th>Sub-modul</th><th>Jumlah Baris</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @foreach ($subModules as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ number_format($counts[$key], 0, ',', '.') }}</td>
                    <td>
                        @if ($batch->isDraft() && $counts[$key] > 0)
                            <form method="POST" action="{{ route('admin.saldo-awal.koreksi.clear', [$batch, $key]) }}"
                                  onsubmit="return confirm('Kosongkan {{ $counts[$key] }} baris {{ $label }}? Tindakan ini tidak bisa dibatalkan.');">
                                @csrf
                                <button type="submit" class="btn-danger">Kosongkan</button>
                            </form>
                        @else
                            <button class="btn-danger" disabled>Kosongkan</button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

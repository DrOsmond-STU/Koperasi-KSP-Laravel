@extends('layouts.app')

@section('title', 'Master Kategori Aktiva Tetap')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
    </style>

    <h2>Master Kategori Aktiva Tetap</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <p style="margin-bottom: 14px;">
        <a href="{{ route('admin.master.fixed-asset-categories.create') }}" class="btn-primary">+ Tambah Kategori</a>
    </p>

    <table class="data-table">
        <thead>
            <tr><th>Kode</th><th>Nama</th><th>Metode Penyusutan Default</th><th>Umur Ekonomis Default</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->code }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->default_depreciation_method === 'garis_lurus' ? 'Garis Lurus' : 'Saldo Menurun' }}</td>
                    <td>{{ $category->default_useful_life_months }} bulan</td>
                    <td>{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada kategori aktiva tetap — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

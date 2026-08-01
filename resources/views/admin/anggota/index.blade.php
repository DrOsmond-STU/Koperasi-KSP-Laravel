@extends('layouts.app')

@section('title', 'Data Anggota')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .search-input { padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; min-width: 240px; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill-aktif { background: var(--leaf); color: var(--pine); }
        .pill-other { background: var(--paper); color: var(--muted); }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: var(--paper); color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .print-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .print-form select { padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
    </style>

    <h2>Data Anggota</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.members.index') }}">
            <input class="search-input" type="text" name="q" value="{{ $search }}" placeholder="Cari nama atau nomor anggota...">
        </form>
        <a href="{{ route('admin.members.create') }}" class="btn-primary">+ Tambah Anggota</a>
    </div>

    <form method="GET" action="{{ route('admin.members.print') }}" class="print-form" style="margin-bottom: 14px;" target="_blank">
        <select name="member_type_id">
            <option value="">Semua Jenis Anggota</option>
            @foreach ($memberTypes as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="branch_id">
            <option value="">Semua Cabang</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-secondary">🖨 Cetak Daftar (PDF)</button>
    </form>

    @can('member_card.print')
        <form method="GET" action="{{ route('admin.members.card.print-bulk') }}" class="print-form" style="margin-bottom: 14px;" target="_blank">
            <select name="member_type_id">
                <option value="">Semua Jenis Anggota</option>
                @foreach ($memberTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            <select name="branch_id">
                <option value="">Semua Cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary">🖨 Cetak Kartu Anggota (Massal)</button>
        </form>
    @endcan

    <table class="data-table">
        <thead>
            <tr>
                <th>No. Anggota</th><th>Nama</th><th>Jenis Anggota</th><th>Cabang</th><th>Status</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $member)
                <tr>
                    <td>{{ $member->member_number }}</td>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->memberType->name }}</td>
                    <td>{{ $member->branch->name }}</td>
                    <td><span class="pill {{ $member->status === 'aktif' ? 'pill-aktif' : 'pill-other' }}">{{ ucfirst($member->status) }}</span></td>
                    <td>
                        <a href="{{ route('admin.members.edit', $member) }}" class="btn-link">Ubah</a>
                        <a href="{{ route('admin.members.card', $member) }}" class="btn-link" style="margin-left: 10px;">Cetak Kartu</a>
                        @can('simpanan.print')
                            <a href="{{ route('admin.print.savings.index', ['member_id' => $member->id]) }}" class="btn-link" style="margin-left: 10px;" target="_blank">Cetak Simpanan</a>
                        @endcan
                        @can('pinjaman.print')
                            <a href="{{ route('admin.print.loans.index', ['member_id' => $member->id]) }}" class="btn-link" style="margin-left: 10px;" target="_blank">Cetak Pinjaman</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada anggota — tambahkan yang pertama.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

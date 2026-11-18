@extends('layouts.app')

@section('title', 'Bagan Akun')

@section('content')
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .btn-danger { color: var(--brick); background: transparent; border: none; text-decoration: none; font-weight: 600; font-size: 13px; cursor: pointer; padding: 0; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill-debit { background: var(--leaf); color: var(--pine); }
        .pill-kredit { background: #FBE7E1; color: var(--brick); }
        .pill-postable { background: var(--leaf); color: var(--pine); }
        .pill-header { background: var(--paper); color: var(--muted); }
        .pill-protected { background: #FBE7E1; color: var(--brick); font-weight: 700; }
        .muted { color: var(--muted); }
    </style>

    <h2>Bagan Akun</h2>
    <p style="color: var(--muted); margin-top: -8px;">Chart of Accounts — hanya Admin Sistem yang bisa menambah, mengubah, atau menghapus akun.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="toolbar">
        <span class="muted">{{ $accounts->count() }} akun</span>
        <span>
            <a href="{{ route('admin.master.chart-of-accounts.export-pdf') }}" target="_blank" class="btn-link">Export PDF</a>
            <a href="{{ route('admin.master.chart-of-accounts.export-excel') }}" class="btn-link" style="margin-left:10px;">Export Excel</a>
            @can('chart_of_account.delete')
                <a href="{{ route('admin.master.chart-of-accounts.purge.form') }}" class="btn-link" style="margin-left:10px; color: var(--brick);">Hapus Massal</a>
            @endcan
            @can('chart_of_account.create')
                <a href="{{ route('admin.master.chart-of-accounts.import.form') }}" class="btn-link" style="margin-left:10px;">Import CSV</a>
            @endcan
            <a href="{{ route('admin.master.chart-of-accounts.create') }}" class="btn-primary" style="margin-left:10px;">+ Tambah Akun</a>
        </span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama Akun</th><th>Tipe</th><th>Grup</th><th>Normal</th><th>Postable</th><th>Induk</th><th>Laporan</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>
                        {{ $account->code }}
                        @if ($account->isProtected())
                            <span class="pill pill-protected" title="Dipakai inti sistem — kode tidak bisa diubah, tidak bisa dihapus">Inti</span>
                        @endif
                    </td>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->type }}</td>
                    <td class="muted">{{ $account->group ?? '—' }}</td>
                    <td><span class="pill {{ $account->normal_balance === 'DEBIT' ? 'pill-debit' : 'pill-kredit' }}">{{ $account->normal_balance }}</span></td>
                    <td><span class="pill {{ $account->is_postable ? 'pill-postable' : 'pill-header' }}">{{ $account->is_postable ? 'Ya' : 'Header' }}</span></td>
                    <td class="muted">{{ $account->parent_code ?? '—' }}</td>
                    <td class="muted">{{ $account->statement === 'NERACA' ? 'Neraca' : 'Laba/Rugi' }}</td>
                    <td>
                        <a href="{{ route('admin.master.chart-of-accounts.edit', $account) }}" class="btn-link">Ubah</a>
                        @unless ($account->isProtected())
                            <form method="POST" action="{{ route('admin.master.chart-of-accounts.destroy', $account) }}" style="display:inline;" onsubmit="return confirm('Hapus akun {{ $account->code }} — {{ $account->name }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">Belum ada akun.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

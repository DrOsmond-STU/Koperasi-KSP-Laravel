@extends('layouts.app')

@section('title', 'Kas per Cabang')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .row-form { display: flex; gap: 8px; align-items: center; }
        .row-form select { padding: 6px 8px; border: 1px solid var(--line); border-radius: 7px; font-size: 13px; min-width: 260px; }
        .btn-save { padding: 6px 12px; background: var(--pine); color: #fff; border: none; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .empty-note { color: var(--muted); font-size: 13px; }
        .not-set { color: var(--brick); font-size: 12px; }
    </style>

    <h2>Kas per Cabang</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 640px;">
        Petakan tiap cabang ke akun kasnya sendiri di Bagan Akun. Dipakai transaksi yang harus
        posting ke kas cabang yang benar (mis. angsuran pinjaman yang diterima Teller) — cabang
        yang belum dipetakan di sini tetap otomatis jatuh ke akun kas konsolidasi (<code>1101</code>),
        jadi mengisi halaman ini bersifat opsional per cabang, tidak wajib sekaligus.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="panel">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cabang</th>
                    <th>Akun Kas Saat Ini</th>
                    <th>Ubah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr>
                        <td>{{ $branch->code }} — {{ $branch->name }}</td>
                        <td>
                            @if ($branch->cashAccount)
                                {{ $branch->cashAccount->code }} — {{ $branch->cashAccount->name }}
                            @else
                                <span class="not-set">Belum diatur (pakai fallback 1101 — Kas)</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.pengaturan.kas-cabang.update', $branch) }}" class="row-form">
                                @csrf
                                @method('PUT')
                                <select name="cash_account_id">
                                    <option value="">— Pakai fallback (1101) —</option>
                                    @foreach ($cashAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($branch->cash_account_id === $account->id)>
                                            {{ $account->code }} — {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-save">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty-note">Belum ada cabang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

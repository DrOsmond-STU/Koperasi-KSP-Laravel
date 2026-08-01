@extends('layouts.portal')

@section('title', 'Pengajuan Penarikan')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 480px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 10px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; }
        .status-tag.menunggu { background: var(--gold-soft); color: var(--gold-deep); }
        .status-tag.disetujui { background: var(--leaf); color: var(--pine-bright); }
        .status-tag.ditolak { background: var(--brick-soft); color: var(--brick); }
        .empty-note { color: var(--muted); font-size: 13px; }
    </style>

    <h2>Pengajuan Penarikan Simpanan</h2>

    @if (session('status'))
        <p style="color: var(--ok); font-size: 13px; margin-bottom: 14px;">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('portal.withdrawal-request.store') }}">
            @csrf
            <div class="field">
                <label>Rekening Simpanan</label>
                <select name="savings_account_id" required>
                    <option value="">— Pilih Rekening —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('savings_account_id') == $account->id)>
                            {{ $account->account_number }} — Saldo Rp {{ number_format($account->balance, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Nominal Penarikan (Rp)</label>
                <input type="number" step="0.01" name="amount" value="{{ old('amount') }}" required>
            </div>
            <button type="submit" class="btn-primary">Kirim Pengajuan</button>
            <p class="hint">Pengajuan ini akan diproses oleh staf koperasi — dana tidak langsung ditransfer secara otomatis.</p>
        </form>
    </div>

    <h3 style="margin-top:28px;">Riwayat Pengajuan</h3>
    <table class="data-table">
        <thead><tr><th>Rekening</th><th>Nominal</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($history as $wr)
                <tr>
                    <td>{{ $wr->savingsAccount->account_number }}</td>
                    <td>Rp {{ number_format($wr->amount, 0, ',', '.') }}</td>
                    <td><span class="status-tag {{ $wr->status }}">{{ ucfirst($wr->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3"><p class="empty-note">Belum ada pengajuan penarikan.</p></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

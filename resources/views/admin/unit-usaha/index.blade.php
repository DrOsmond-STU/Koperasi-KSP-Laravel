@extends('layouts.app')

@section('title', 'Unit Usaha Lain')

@section('content')
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 9px 16px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        @media (max-width: 980px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>

    <h2>Unit Usaha Lain</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="panel" style="margin-bottom: 20px;">
        <h3>Laba/Rugi per Unit Usaha</h3>
        <table class="data-table">
            <thead><tr><th>Unit Usaha</th><th>Pendapatan</th><th>Beban</th><th>Laba/Rugi</th></tr></thead>
            <tbody>
                @forelse ($units as $row)
                    <tr>
                        <td>{{ $row['unit']->name }}</td>
                        <td>Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($row['beban'], 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($row['laba_rugi'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada unit usaha aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h3>Tambah Unit Usaha</h3>
            <form method="POST" action="{{ route('admin.unit-usaha.store') }}">
                @csrf
                <div class="field"><label>Kode</label><input type="text" name="code" required></div>
                <div class="field"><label>Nama</label><input type="text" name="name" required></div>
                <div class="field">
                    <label>Akun COA — Pendapatan</label>
                    <select name="coa_revenue_account_id" required>
                        @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Akun COA — Beban</label>
                    <select name="coa_expense_account_id" required>
                        @foreach ($postableAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">Simpan</button>
            </form>
        </div>

        <div class="panel">
            <h3>Catat Transaksi</h3>
            <form method="POST" action="{{ route('admin.unit-usaha.transaksi') }}">
                @csrf
                <div class="field">
                    <label>Unit Usaha</label>
                    <select name="business_unit_id" required>
                        @foreach ($allUnits as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cabang</label>
                    <select name="branch_id" required>
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Jenis</label>
                    <select name="type" required>
                        <option value="pendapatan">Pendapatan</option>
                        <option value="beban">Beban</option>
                    </select>
                </div>
                <div class="field"><label>Nominal (Rp)</label><input type="number" step="0.01" name="amount" required></div>
                <div class="field"><label>Keterangan</label><input type="text" name="description"></div>
                <button type="submit" class="btn-primary">Simpan Transaksi</button>
            </form>
        </div>
    </div>
@endsection

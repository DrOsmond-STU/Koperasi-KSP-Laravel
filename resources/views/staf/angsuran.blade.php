@extends('layouts.app')

@section('title', 'Catat Angsuran')

@section('content')
    <style>
        .angsuran-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 12px; }
        .feed-item { padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 13px; }
        .feed-item-row { display: flex; justify-content: space-between; align-items: center; }
        @media (max-width: 980px) { .angsuran-grid { grid-template-columns: 1fr; } }
    </style>

    <h2>Catat Angsuran</h2>
    <p style="color: var(--muted); margin-top: -8px;">Pembayaran angsuran tunai yang diterima di loket — langsung tercatat, bukan lewat gateway pembayaran mandiri.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="angsuran-grid">
        <div class="panel">
            <h3>Pembayaran Angsuran</h3>
            <form method="POST" action="{{ route('staf.angsuran.preview') }}">
                @csrf
                <div class="field">
                    <label>Pinjaman</label>
                    <select name="loan_id" required class="js-searchable">
                        <option value="">— Pilih Pinjaman —</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}" @selected(old('loan_id', $preselectedLoanId) == $loan->id)>
                                {{ $loan->loan_number }} — {{ $loan->member->name }} ({{ $loan->loanProduct->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Nominal Bayar (Rp)</label>
                    <input type="number" step="0.01" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div class="field">
                    <label>Keterangan (opsional)</label>
                    <input type="text" name="description" value="{{ old('description') }}">
                </div>
                <button type="submit" class="btn-primary">Lihat Rincian Alokasi</button>
            </form>
        </div>

        <div class="panel">
            <h3>Angsuran Hari Ini</h3>
            @forelse ($recentRepayments as $repayment)
                <div class="feed-item">
                    <div class="feed-item-row">
                        <span>{{ $repayment->loan->member->name }} — {{ $repayment->loan->loan_number }}</span>
                        <span>Rp {{ number_format($repayment->amount, 0, ',', '.') }}</span>
                    </div>
                    <a href="{{ route('admin.print.loan-repayment.show', $repayment) }}" target="_blank" style="font-size:11px;">Cetak Bukti</a>
                </div>
            @empty
                <p style="color: var(--muted); font-size: 13px;">Belum ada angsuran dicatat hari ini.</p>
            @endforelse
        </div>
    </div>
@endsection

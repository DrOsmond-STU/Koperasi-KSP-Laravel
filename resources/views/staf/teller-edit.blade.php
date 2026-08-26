@extends('layouts.app')

@section('title', 'Edit Transaksi Simpanan')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; max-width: 520px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-ghost { padding: 10px 18px; background: transparent; color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; text-decoration: none; display: inline-block; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 12px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .original-note { background: var(--paper); border: 1px solid var(--line); border-radius: 9px; padding: 10px 14px; font-size: 12px; color: var(--muted); margin-bottom: 18px; }
        .original-note strong { color: var(--pine-ink); }
    </style>

    <h2>Edit Transaksi — {{ $transaction->savingsAccount->account_number }}</h2>
    <p style="color: var(--muted); margin-top: -8px;">{{ $transaction->savingsAccount->member->name }}</p>

    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="panel">
        <p class="original-note">
            Tersimpan sekarang: <strong>{{ ucfirst($transaction->type) }}</strong>,
            Rp {{ number_format($transaction->amount, 0, ',', '.') }},
            tanggal <strong>{{ $transaction->transactionOn()->format('d/m/Y') }}</strong>@if ($transaction->description) , keterangan "{{ $transaction->description }}" @endif.
            Mengedit akan MEMBATALKAN baris ini (jurnal dibalik) lalu mencatat baris baru dengan nilai di bawah —
            rekening penerima TIDAK bisa diganti dari sini.
        </p>

        <form method="POST" action="{{ route('staf.teller.update', $transaction) }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label>Tanggal Transaksi</label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', $transaction->transactionOn()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
            </div>
            <div class="field">
                <label>Jenis Transaksi</label>
                <select name="type" required>
                    <option value="setor" @selected(old('type', $transaction->type) === 'setor')>Setor</option>
                    <option value="tarik" @selected(old('type', $transaction->type) === 'tarik')>Tarik</option>
                </select>
            </div>
            <div class="field">
                <label>Nominal (Rp)</label>
                <input type="number" step="0.01" name="amount" value="{{ old('amount', $transaction->amount) }}" required>
            </div>
            <div class="field">
                <label>Keterangan (opsional)</label>
                <input type="text" name="description" value="{{ old('description', $transaction->description) }}">
            </div>
            <div class="field">
                <label>Alasan Edit</label>
                <input type="text" name="reason" value="{{ old('reason') }}" placeholder="Mis. salah ketik nominal" required>
            </div>
            <button type="submit" class="btn-primary">Simpan Perubahan</button>
            <a href="{{ route('staf.teller.history') }}" class="btn-ghost">Batal</a>
        </form>
    </div>
@endsection

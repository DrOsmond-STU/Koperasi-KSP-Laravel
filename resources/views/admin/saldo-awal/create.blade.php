@extends('layouts.app')

@section('title', 'Batch Migrasi Baru')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 420px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
    </style>

    <h2>Batch Migrasi Saldo Awal Baru</h2>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.saldo-awal.store') }}">
            @csrf
            <div class="field">
                <label>Cabang</label>
                <select name="branch_id" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Tanggal Cut-off Migrasi</label>
                <input type="date" name="cutoff_date" required>
            </div>
            <button type="submit" class="btn-primary">Buat Batch</button>
        </form>
    </div>
@endsection

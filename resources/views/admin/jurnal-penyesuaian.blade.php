@extends('layouts.app')

@section('title', 'Jurnal Penyesuaian')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 620px; margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-danger { padding: 10px 18px; background: #A8472F; color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: #A8472F; font-size: 12px; margin-top: 4px; }
        .status-msg { color: #2E7D52; font-size: 13px; margin-bottom: 14px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); }
    </style>

    <h2>Jurnal Penyesuaian</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-text" style="margin-bottom:14px;">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="form-card">
        <p class="hint">Koreksi jurnal membuat jurnal balik — entri asal tidak dihapus. Wajib konfirmasi ulang kata sandi Anda.</p>

        <form method="GET" action="{{ route('admin.jurnal-penyesuaian.create') }}">
            <div class="field">
                <label>Pilih Entri Jurnal untuk Dikoreksi</label>
                <select name="entry_id" onchange="this.form.submit()">
                    <option value="">— Pilih Entri —</option>
                    @foreach ($entries as $entry)
                        <option value="{{ $entry->id }}" {{ $selectedEntry?->id === $entry->id ? 'selected' : '' }}>
                            #{{ $entry->id }} — {{ $entry->entry_date->translatedFormat('d M Y') }} — {{ $entry->description }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if ($selectedEntry)
        <div class="form-card">
            <h3>Entri #{{ $selectedEntry->id }}</h3>
            <table class="data-table">
                <thead><tr><th>Akun</th><th>Debit</th><th>Kredit</th></tr></thead>
                <tbody>
                    @foreach ($selectedEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                            <td>Rp {{ number_format((float) $line->debit, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $line->credit, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form method="POST" action="{{ route('admin.jurnal-penyesuaian.store', $selectedEntry) }}" style="margin-top: 16px;">
                @csrf
                <div class="field">
                    <label>Alasan Koreksi</label>
                    <input type="text" name="reason" required>
                </div>
                <div class="field">
                    <label>Konfirmasi Kata Sandi</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn-danger">Posting Jurnal Balik</button>
            </form>
        </div>
    @endif
@endsection

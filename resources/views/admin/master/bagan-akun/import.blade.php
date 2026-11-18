@extends('layouts.app')

@section('title', 'Import Bagan Akun')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 700px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .hint { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.55; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; font-size: 13px; line-height: 1.6; margin-bottom: 18px; }
        .err-box { background: rgba(0,0,0,.02); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px; max-height: 340px; overflow: auto; }
        .err-box li { margin-bottom: 6px; font-size: 13px; }
    </style>

    <h2>Import Bagan Akun</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.master.chart-of-accounts.index') }}" class="btn-link">&larr; Kembali ke Bagan Akun</a>
    </p>

    @if (session('error'))
        <div class="error-text">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="err-box">
            <strong>Baris yang gagal ({{ count(session('import_errors')) }}):</strong>
            <ul>
                @foreach (session('import_errors') as $failure)
                    <li>Baris {{ $failure['row'] }} — {{ implode('; ', $failure['errors']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="notice">
        Saat ini ada <strong>{{ number_format($jumlahAkun, 0, ',', '.') }} akun</strong>
        ({{ number_format($jumlahPostable, 0, ',', '.') }} postable).
        <br>
        Import ini <strong>menambah atau memperbarui per kode akun</strong> — bagan yang sudah ada tidak dihapus.
        Akun inti yang dipakai langsung oleh sistem ({{ implode(', ', \App\Models\ChartOfAccount::PROTECTED_CODES) }})
        ditolak bila ada di file, supaya posting kas, koreksi persediaan, dan pelepasan aset tidak rusak.
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.master.chart-of-accounts.import') }}" enctype="multipart/form-data">
            @csrf

            <div class="field">
                <label>File CSV</label>
                <input type="file" name="file" accept=".csv,text/csv" required>
                <div class="hint">
                    Kolom: <code>{{ implode(', ', \App\Services\Accounting\ChartOfAccountImportService::HEADERS) }}</code>.
                    <br>
                    <code>type</code>: ASET / LIABILITAS / EKUITAS / PENDAPATAN / BEBAN ·
                    <code>normal_balance</code>: DEBIT / KREDIT ·
                    <code>statement</code>: NERACA / LABA_RUGI ·
                    <code>is_postable</code>: TRUE / FALSE.
                    <br>
                    <code>parent_code</code> boleh menunjuk akun di file yang sama maupun akun yang sudah ada.
                </div>
            </div>

            <div class="field">
                <label>Mode</label>
                <select name="mode" required>
                    <option value="all_or_nothing">All-or-nothing — simpan hanya bila tidak ada error</option>
                    <option value="partial">Partial — simpan baris yang valid saja</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Validasi &amp; Import</button>
        </form>
    </div>
@endsection

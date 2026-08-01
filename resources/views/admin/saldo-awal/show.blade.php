@extends('layouts.app')

@section('title', 'Detail Batch Migrasi')

@section('content')
    <style>
        .banner { border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; }
        .banner-ok { background: var(--leaf); color: var(--pine-bright); border: 1px solid var(--pine); }
        .banner-bad { background: var(--brick-soft); color: var(--brick); border: 1px solid var(--brick); }
        .banner-info { background: var(--surface); border: 1px solid var(--line); color: var(--muted); }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); }
        .import-form { display: flex; gap: 10px; align-items: center; margin-top: 10px; flex-wrap: wrap; }
        .btn-primary { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .btn-secondary { padding: 7px 12px; background: transparent; color: var(--pine); border: 1px solid var(--pine); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-danger { padding: 4px 8px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 11px; }
        .btn-lock { padding: 12px 22px; background: var(--gold-deep); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .error-list { background: var(--brick-soft); border-radius: 8px; padding: 10px 14px; margin-top: 10px; font-size: 12px; color: var(--brick); }
        .locked-badge { display: inline-block; background: var(--leaf); color: var(--pine-bright); padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .panel-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .toggle-link { font-size: 12px; color: var(--pine); cursor: pointer; font-weight: 600; text-decoration: underline; background: none; border: none; padding: 0; }
        .manual-form { display: none; margin-top: 12px; padding: 12px; background: var(--paper); border-radius: 10px; }
        .manual-form.open { display: block; }
        .manual-form .field-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 10px; }
        .manual-form label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 3px; }
        .manual-form input, .manual-form select { width: 100%; padding: 6px 8px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; box-sizing: border-box; }
        .field-guide { font-size: 11px; color: var(--muted); margin-top: 8px; }
        .field-guide code { background: var(--paper); padding: 1px 5px; border-radius: 4px; }
        details.guide summary { cursor: pointer; font-weight: 700; font-size: 13px; }
        details.guide ul { margin: 8px 0; padding-left: 20px; font-size: 12px; }
        details.guide { margin-bottom: 20px; }
    </style>

    <h2>Batch Migrasi — {{ $batch->branch->name }} (Cut-off {{ $batch->cutoff_date->translatedFormat('d M Y') }})</h2>

    @if ($batch->status === 'locked')
        <p><span class="locked-badge">🔒 Dikunci oleh {{ $batch->lockedBy->name ?? '-' }} pada {{ $batch->locked_at?->translatedFormat('d M Y H:i') }}</span></p>
    @endif

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <div class="banner banner-bad">{{ session('error') }}</div>
    @endif

    {{-- Panduan Import --}}
    <details class="guide banner banner-info">
        <summary>📋 Panduan Import CSV — Aturan Umum</summary>
        <ul>
            <li>Encoding file: <strong>UTF-8</strong>.</li>
            <li>Delimiter: <strong>koma ( , )</strong>.</li>
            <li>Angka desimal pakai <strong>titik</strong> (mis. <code>1500000.00</code>), tanpa pemisah ribuan.</li>
            <li>Format tanggal: <strong>YYYY-MM-DD</strong> (mis. <code>2026-01-01</code>).</li>
            <li>Baris header wajib ada dan namanya harus persis seperti template.</li>
            <li>Kolom opsional yang kosong: <strong>dikosongkan saja</strong>, jangan diisi teks <code>NULL</code>.</li>
            <li>Maksimal <strong>10.000 baris</strong> per file.</li>
            <li>Urutan import yang disarankan: <strong>Master dasar → COA → Simpanan → Pinjaman → Angsuran → UPF → Persediaan</strong>.</li>
            <li>Setiap panel di bawah punya tombol <strong>Unduh Template</strong> — sudah berisi header kolom yang benar + 1 baris contoh.</li>
        </ul>
    </details>

    {{-- Reconciliation Banner --}}
    <div class="banner {{ $report->isFullyBalanced() ? 'banner-ok' : 'banner-bad' }}">
        @if ($report->isFullyBalanced())
            ✓ Saldo awal balance — Debit Rp {{ number_format($report->coaDebitTotal, 0, ',', '.') }} = Kredit Rp {{ number_format($report->coaCreditTotal, 0, ',', '.') }}. Siap dikunci.
        @else
            ✗ Belum balance — Debit Rp {{ number_format($report->coaDebitTotal, 0, ',', '.') }} vs Kredit Rp {{ number_format($report->coaCreditTotal, 0, ',', '.') }}.
            @if (count($report->savingsDiscrepancies) > 0) Ada {{ count($report->savingsDiscrepancies) }} selisih Simpanan vs COA. @endif
            @if (count($report->loansDiscrepancies) > 0) Ada {{ count($report->loansDiscrepancies) }} selisih Pinjaman vs COA. @endif
            @if (count($report->upfDiscrepancies) > 0) Ada {{ count($report->upfDiscrepancies) }} selisih UPF vs COA. @endif
            @if (count($report->stockDiscrepancies) > 0) Ada {{ count($report->stockDiscrepancies) }} selisih Persediaan vs COA. @endif
        @endif
    </div>

    @if (session('import_errors') && count(session('import_errors')) > 0)
        <div class="error-list">
            <strong>Baris gagal:</strong>
            <ul>
                @foreach (session('import_errors') as $err)
                    <li>Baris {{ $err['row'] }}: {{ implode('; ', $err['errors']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $isDraft = $batch->status === 'draft';
    @endphp

    {{-- ================= SIMPANAN ================= --}}
    <div class="panel">
        <div class="panel-head">
            <h3>Saldo Awal Simpanan ({{ $batch->savings->count() }} baris)</h3>
            @if ($isDraft)
                <button type="button" class="toggle-link" onclick="document.getElementById('form-savings').classList.toggle('open')">+ Tambah manual</button>
            @endif
        </div>

        @if ($batch->savings->count() > 0)
            <table class="data-table">
                <thead><tr><th>Anggota</th><th>Produk</th><th>No. Rekening</th><th>Saldo</th><th>Keterangan</th>@if($isDraft)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($batch->savings as $row)
                    <tr>
                        <td>{{ $row->member?->name }} ({{ $row->member?->member_number }})</td>
                        <td>{{ $row->savingsProduct?->name }}</td>
                        <td>{{ $row->account_number }}</td>
                        <td>Rp {{ number_format($row->balance, 0, ',', '.') }}</td>
                        <td>{{ $row->notes }}</td>
                        @if ($isDraft)
                        <td>
                            <form method="POST" action="{{ route('admin.saldo-awal.row.destroy', [$batch, 'savings', $row->id]) }}" onsubmit="return confirm('Hapus baris ini?');" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($isDraft)
            <form id="form-savings" class="manual-form" method="POST" action="{{ route('admin.saldo-awal.row.savings.store', $batch) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Anggota</label>
                        <select name="member_id" required class="js-searchable">
                            <option value="">- pilih -</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->member_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Produk Simpanan</label>
                        <select name="savings_product_id" required>
                            <option value="">- pilih -</option>
                            @foreach ($savingsProducts as $p)
                                <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>No. Rekening (opsional)</label>
                        <input type="text" name="account_number" maxlength="50">
                    </div>
                    <div>
                        <label>Saldo Awal</label>
                        <input type="number" step="0.01" min="0" name="balance" required>
                    </div>
                    <div>
                        <label>Keterangan (opsional)</label>
                        <input type="text" name="notes" maxlength="255">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Baris</button>
            </form>

            <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, 'savings']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv" required>
                <select name="mode">
                    <option value="all_or_nothing">All-or-nothing</option>
                    <option value="partial">Partial</option>
                </select>
                <button type="submit" class="btn-primary">Import CSV</button>
                <a class="btn-secondary" href="{{ route('admin.saldo-awal.template', 'savings') }}">Unduh Template</a>
            </form>
            <p class="field-guide">Kolom CSV: <code>kode_anggota, kode_produk_simpanan, no_rekening, saldo_awal, tanggal_saldo_awal, keterangan</code> — contoh: <code>AGT-0001, SIM-SUKARELA, , 1500000.00, 2026-01-01, Migrasi dari sistem lama</code></p>
        @endif
    </div>

    {{-- ================= PINJAMAN ================= --}}
    <div class="panel">
        <div class="panel-head">
            <h3>Saldo Awal Pinjaman ({{ $batch->loans->count() }} baris)</h3>
            @if ($isDraft)
                <button type="button" class="toggle-link" onclick="document.getElementById('form-loans').classList.toggle('open')">+ Tambah manual</button>
            @endif
        </div>

        @if ($batch->loans->count() > 0)
            <table class="data-table">
                <thead><tr><th>Anggota</th><th>Produk</th><th>No. Lama</th><th>Sisa Pokok</th><th>Kolektibilitas</th>@if($isDraft)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($batch->loans as $row)
                    <tr>
                        <td>{{ $row->member?->name }} ({{ $row->member?->member_number }})</td>
                        <td>{{ $row->loanProduct?->name }}</td>
                        <td>{{ $row->external_loan_number }}</td>
                        <td>Rp {{ number_format($row->outstanding_principal, 0, ',', '.') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $row->collectibility)) }}</td>
                        @if ($isDraft)
                        <td>
                            <form method="POST" action="{{ route('admin.saldo-awal.row.destroy', [$batch, 'loans', $row->id]) }}" onsubmit="return confirm('Hapus baris ini? Angsuran terkait tidak ikut terhapus otomatis.');" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($isDraft)
            <form id="form-loans" class="manual-form" method="POST" action="{{ route('admin.saldo-awal.row.loans.store', $batch) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Anggota</label>
                        <select name="member_id" required class="js-searchable">
                            <option value="">- pilih -</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->member_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Produk Pinjaman</label>
                        <select name="loan_product_id" required>
                            <option value="">- pilih -</option>
                            @foreach ($loanProducts as $p)
                                <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>No. Pinjaman Lama (opsional)</label>
                        <input type="text" name="external_loan_number" maxlength="50">
                    </div>
                    <div>
                        <label>Tanggal Akad</label>
                        <input type="date" name="disbursement_date" required>
                    </div>
                    <div>
                        <label>Plafon Awal</label>
                        <input type="number" step="0.01" min="0" name="original_principal" required>
                    </div>
                    <div>
                        <label>Sisa Pokok</label>
                        <input type="number" step="0.01" min="0" name="outstanding_principal" required>
                    </div>
                    <div>
                        <label>Sisa Jasa Berjalan (opsional)</label>
                        <input type="number" step="0.01" min="0" name="outstanding_interest">
                    </div>
                    <div>
                        <label>Tenor (bulan)</label>
                        <input type="number" min="1" name="tenor_months" required>
                    </div>
                    <div>
                        <label>Sisa Tenor (bulan)</label>
                        <input type="number" min="0" name="remaining_tenor_months" required>
                    </div>
                    <div>
                        <label>Angsuran ke-</label>
                        <input type="number" min="1" name="next_installment_number" required>
                    </div>
                    <div>
                        <label>Tanggal Jatuh Tempo Berikutnya</label>
                        <input type="date" name="next_due_date" required>
                    </div>
                    <div>
                        <label>Kolektibilitas</label>
                        <select name="collectibility" required>
                            <option value="">- pilih -</option>
                            <option value="lancar">Lancar</option>
                            <option value="kurang_lancar">Kurang Lancar</option>
                            <option value="diragukan">Diragukan</option>
                            <option value="macet">Macet</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Baris</button>
            </form>

            <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, 'loans']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv" required>
                <select name="mode">
                    <option value="all_or_nothing">All-or-nothing</option>
                    <option value="partial">Partial</option>
                </select>
                <button type="submit" class="btn-primary">Import CSV</button>
                <a class="btn-secondary" href="{{ route('admin.saldo-awal.template', 'loans') }}">Unduh Template</a>
            </form>
            <p class="field-guide">Kolom CSV: <code>kode_anggota, kode_produk_pinjaman, no_pinjaman_lama, tanggal_akad, plafon_awal, sisa_pokok, sisa_jasa_berjalan, tenor_bulan, sisa_tenor, angsuran_ke, tanggal_jatuh_tempo_berikutnya, kolektibilitas</code></p>
        @endif
    </div>

    {{-- ================= ANGSURAN ================= --}}
    <div class="panel">
        <div class="panel-head">
            <h3>Saldo Awal Angsuran ({{ $batch->loans->sum(fn($l) => $l->installments->count()) }} baris)</h3>
            @if ($isDraft)
                <button type="button" class="toggle-link" onclick="document.getElementById('form-installments').classList.toggle('open')">+ Tambah manual</button>
            @endif
        </div>

        @php $allInstallments = $batch->loans->flatMap(fn($l) => $l->installments->map(fn($i) => [$i, $l])); @endphp
        @if ($allInstallments->count() > 0)
            <table class="data-table">
                <thead><tr><th>No. Pinjaman Lama</th><th>Angsuran ke-</th><th>Jatuh Tempo</th><th>Pokok</th><th>Jasa</th><th>Denda</th><th>Status</th>@if($isDraft)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($allInstallments as [$row, $loan])
                    <tr>
                        <td>{{ $loan->external_loan_number }}</td>
                        <td>{{ $row->installment_number }}</td>
                        <td>{{ $row->due_date->format('d-m-Y') }}</td>
                        <td>Rp {{ number_format($row->principal_amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($row->interest_amount, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($row->penalty_amount, 0, ',', '.') }}</td>
                        <td>{{ $row->status === 'belum_bayar' ? 'Belum Bayar' : 'Sebagian' }}</td>
                        @if ($isDraft)
                        <td>
                            <form method="POST" action="{{ route('admin.saldo-awal.row.destroy', [$batch, 'installments', $row->id]) }}" onsubmit="return confirm('Hapus baris ini?');" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($isDraft)
            <form id="form-installments" class="manual-form" method="POST" action="{{ route('admin.saldo-awal.row.installments.store', $batch) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Pinjaman (No. Lama)</label>
                        <select name="opening_balance_loan_id" required>
                            <option value="">- pilih -</option>
                            @foreach ($batch->loans as $loan)
                                <option value="{{ $loan->id }}">{{ $loan->external_loan_number ?? ('#'.$loan->id) }} — {{ $loan->member?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Angsuran ke-</label>
                        <input type="number" min="1" name="installment_number" required>
                    </div>
                    <div>
                        <label>Jatuh Tempo</label>
                        <input type="date" name="due_date" required>
                    </div>
                    <div>
                        <label>Nominal Pokok</label>
                        <input type="number" step="0.01" min="0" name="principal_amount" required>
                    </div>
                    <div>
                        <label>Nominal Jasa</label>
                        <input type="number" step="0.01" min="0" name="interest_amount" required>
                    </div>
                    <div>
                        <label>Nominal Denda (opsional)</label>
                        <input type="number" step="0.01" min="0" name="penalty_amount">
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status" required>
                            <option value="">- pilih -</option>
                            <option value="belum_bayar">Belum Bayar</option>
                            <option value="sebagian">Sebagian</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Baris</button>
            </form>

            @if ($batch->loans->count() === 0)
                <p class="field-guide">Tambahkan minimal satu baris Pinjaman terlebih dahulu sebelum mengisi Angsuran.</p>
            @endif

            <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, 'installments']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv" required>
                <select name="mode">
                    <option value="all_or_nothing">All-or-nothing</option>
                    <option value="partial">Partial</option>
                </select>
                <button type="submit" class="btn-primary">Import CSV</button>
                <a class="btn-secondary" href="{{ route('admin.saldo-awal.template', 'installments') }}">Unduh Template</a>
            </form>
            <p class="field-guide">Kolom CSV: <code>no_pinjaman_lama, angsuran_ke, tanggal_jatuh_tempo, nominal_pokok, nominal_jasa, nominal_denda, status</code> — <code>no_pinjaman_lama</code> harus sudah ada di data Pinjaman batch ini.</p>
        @endif
    </div>

    {{-- ================= COA / BUKU BESAR ================= --}}
    <div class="panel">
        <div class="panel-head">
            <h3>Saldo Awal Buku Besar / COA ({{ $batch->coaLines->count() }} baris)</h3>
            @if ($isDraft)
                <button type="button" class="toggle-link" onclick="document.getElementById('form-coa').classList.toggle('open')">+ Tambah manual</button>
            @endif
        </div>

        @if ($batch->coaLines->count() > 0)
            <table class="data-table">
                <thead><tr><th>Akun</th><th>Posisi</th><th>Saldo</th>@if($isDraft)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($batch->coaLines as $row)
                    <tr>
                        <td>{{ $row->account?->code }} — {{ $row->account?->name }}</td>
                        <td>{{ ucfirst($row->position) }}</td>
                        <td>Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                        @if ($isDraft)
                        <td>
                            <form method="POST" action="{{ route('admin.saldo-awal.row.destroy', [$batch, 'coa', $row->id]) }}" onsubmit="return confirm('Hapus baris ini?');" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($isDraft)
            <form id="form-coa" class="manual-form" method="POST" action="{{ route('admin.saldo-awal.row.coa.store', $batch) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Akun (COA)</label>
                        <select name="chart_of_account_id" required class="js-searchable">
                            <option value="">- pilih -</option>
                            @foreach ($postableAccounts as $a)
                                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Posisi</label>
                        <select name="position" required>
                            <option value="">- pilih -</option>
                            <option value="debit">Debit</option>
                            <option value="kredit">Kredit</option>
                        </select>
                    </div>
                    <div>
                        <label>Saldo</label>
                        <input type="number" step="0.01" min="0" name="amount" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Baris</button>
            </form>

            <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, 'coa']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv" required>
                <select name="mode">
                    <option value="all_or_nothing">All-or-nothing</option>
                    <option value="partial">Partial</option>
                </select>
                <button type="submit" class="btn-primary">Import CSV</button>
                <a class="btn-secondary" href="{{ route('admin.saldo-awal.template', 'coa') }}">Unduh Template</a>
            </form>
            <p class="field-guide">Kolom CSV: <code>kode_akun, posisi, saldo, tanggal_saldo_awal</code> — contoh: <code>1-1000, Debit, 50000000.00, 2026-01-01</code>. Total Debit harus sama dengan total Kredit sebelum batch bisa dikunci.</p>
        @endif
    </div>

    {{-- ================= UPF ================= --}}
    <div class="panel">
        <div class="panel-head">
            <h3>Saldo Awal UPF — Pendapatan per Jenis Retribusi ({{ $batch->upf->count() }} baris)</h3>
            @if ($isDraft)
                <button type="button" class="toggle-link" onclick="document.getElementById('form-upf').classList.toggle('open')">+ Tambah manual</button>
            @endif
        </div>

        @if ($batch->upf->count() > 0)
            <table class="data-table">
                <thead><tr><th>Jenis Retribusi</th><th>Saldo Pendapatan</th><th>Keterangan</th>@if($isDraft)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($batch->upf as $row)
                    <tr>
                        <td>{{ $row->retributionType?->code }} — {{ $row->retributionType?->name }}</td>
                        <td>Rp {{ number_format($row->amount, 0, ',', '.') }}</td>
                        <td>{{ $row->notes }}</td>
                        @if ($isDraft)
                        <td>
                            <form method="POST" action="{{ route('admin.saldo-awal.row.destroy', [$batch, 'upf', $row->id]) }}" onsubmit="return confirm('Hapus baris ini?');" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($isDraft)
            <form id="form-upf" class="manual-form" method="POST" action="{{ route('admin.saldo-awal.row.upf.store', $batch) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Jenis Retribusi</label>
                        <select name="retribution_type_id" required>
                            <option value="">- pilih -</option>
                            @foreach ($retributionTypes as $rt)
                                <option value="{{ $rt->id }}">{{ $rt->code }} — {{ $rt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Saldo Pendapatan Awal</label>
                        <input type="number" step="0.01" min="0" name="amount" required>
                    </div>
                    <div>
                        <label>Keterangan (opsional)</label>
                        <input type="text" name="notes" maxlength="255">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Baris</button>
            </form>

            <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, 'upf']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv" required>
                <select name="mode">
                    <option value="all_or_nothing">All-or-nothing</option>
                    <option value="partial">Partial</option>
                </select>
                <button type="submit" class="btn-primary">Import CSV</button>
                <a class="btn-secondary" href="{{ route('admin.saldo-awal.template', 'upf') }}">Unduh Template</a>
            </form>
            <p class="field-guide">Kolom CSV: <code>kode_jenis_retribusi, saldo_awal, keterangan</code> — contoh: <code>KEB, 250000.00, Migrasi dari sistem lama</code>. Saldo ini akan direkonsiliasi ke akun pendapatan (kredit) milik jenis retribusi terkait pada baris COA.</p>
        @endif
    </div>

    {{-- ================= PERSEDIAAN ================= --}}
    <div class="panel">
        <div class="panel-head">
            <h3>Saldo Awal Persediaan ({{ $batch->stock->count() }} baris)</h3>
            @if ($isDraft)
                <button type="button" class="toggle-link" onclick="document.getElementById('form-stock').classList.toggle('open')">+ Tambah manual</button>
            @endif
        </div>

        @if ($batch->stock->count() > 0)
            <table class="data-table">
                <thead><tr><th>Barang</th><th>Qty</th><th>Harga Satuan</th><th>Nilai</th><th>Keterangan</th>@if($isDraft)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($batch->stock as $row)
                    <tr>
                        <td>{{ $row->product?->code }} — {{ $row->product?->name }}</td>
                        <td>{{ number_format($row->qty, 4, ',', '.') }}</td>
                        <td>Rp {{ number_format($row->unit_cost, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($row->qty * $row->unit_cost, 0, ',', '.') }}</td>
                        <td>{{ $row->notes }}</td>
                        @if ($isDraft)
                        <td>
                            <form method="POST" action="{{ route('admin.saldo-awal.row.destroy', [$batch, 'stock', $row->id]) }}" onsubmit="return confirm('Hapus baris ini?');" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($isDraft)
            <form id="form-stock" class="manual-form" method="POST" action="{{ route('admin.saldo-awal.row.stock.store', $batch) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Barang</label>
                        <select name="product_id" required class="js-searchable">
                            <option value="">- pilih -</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Qty Awal</label>
                        <input type="number" step="0.0001" min="0" name="qty" required>
                    </div>
                    <div>
                        <label>Harga Satuan</label>
                        <input type="number" step="0.01" min="0" name="unit_cost" required>
                    </div>
                    <div>
                        <label>Keterangan (opsional)</label>
                        <input type="text" name="notes" maxlength="255">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Simpan Baris</button>
            </form>

            <form class="import-form" method="POST" action="{{ route('admin.saldo-awal.import', [$batch, 'stock']) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="file" accept=".csv" required>
                <select name="mode">
                    <option value="all_or_nothing">All-or-nothing</option>
                    <option value="partial">Partial</option>
                </select>
                <button type="submit" class="btn-primary">Import CSV</button>
                <a class="btn-secondary" href="{{ route('admin.saldo-awal.template', 'stock') }}">Unduh Template</a>
            </form>
            <p class="field-guide">Kolom CSV: <code>kode_barang, qty, harga_satuan, keterangan</code> — contoh: <code>BRG-0001, 50.0000, 15000.00, Migrasi dari sistem lama</code>. Nilai (qty x harga) akan direkonsiliasi ke akun persediaan (debit) milik barang terkait pada baris COA. Saat migrasi dikunci, setiap baris dicatat sebagai transaksi awal di Kartu Persediaan (Kartu Persediaan Rincian akan menampilkannya sebagai Saldo Awal).</p>
        @endif
    </div>

    @if ($batch->status === 'draft')
        <form method="POST" action="{{ route('admin.saldo-awal.lock', $batch) }}" onsubmit="return confirm('Kunci migrasi ini? Tindakan tidak bisa dibatalkan.');">
            @csrf
            <button type="submit" class="btn-lock" {{ $report->isFullyBalanced() ? '' : 'disabled' }}>Kunci Migrasi & Buat Jurnal Pembukaan</button>
        </form>
    @endif
@endsection

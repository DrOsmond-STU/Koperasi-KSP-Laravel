@extends('layouts.app')

@section('title', 'Jurnal Umum')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 760px; margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .field-row { display: flex; gap: 14px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-secondary { padding: 8px 14px; background: transparent; color: var(--pine-bright); border: 1px solid var(--pine); border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 12px; }
        .btn-remove-row { padding: 5px 10px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 11px; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); }

        .lines-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .lines-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); padding: 0 8px 6px; }
        .lines-table td { padding: 4px 8px 4px 0; vertical-align: top; }
        .lines-table select, .lines-table input { width: 100%; box-sizing: border-box; padding: 7px 9px; border: 1px solid var(--line); border-radius: 8px; font-size: 12.5px; }

        .balance-bar {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;
            padding: 10px 14px; border-radius: 9px; font-size: 13px; font-weight: 700; margin: 12px 0;
            background: var(--leaf); color: var(--pine-bright); border: 1px solid var(--line);
        }
        .balance-bar.unbalanced { background: var(--brick-soft); color: var(--brick); }
    </style>

    <h2>Jurnal Umum</h2>
    <p class="hint" style="margin-top: -8px; margin-bottom: 16px;">Pembukuan debit/kredit manual untuk transaksi di luar simpan-pinjam (mis. beban kantor, pendapatan lain, koreksi saldo). Semua entri wajib seimbang (total debit = total kredit) dan otomatis muncul di Jurnal &amp; Buku Besar.</p>

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
        <form method="POST" action="{{ route('admin.jurnal-umum.store') }}" id="general-journal-form">
            @csrf

            <div class="field-row">
                <div class="field">
                    <label>Tanggal</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                </div>
                <div class="field">
                    <label>Cabang</label>
                    <select name="branch_id" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $selectedBranchId) === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Keterangan</label>
                <input type="text" name="description" value="{{ old('description') }}" placeholder="mis. Bayar listrik kantor Juli 2026" required>
            </div>

            <table class="lines-table">
                <thead>
                    <tr><th style="width:44%;">Akun</th><th style="width:22%;">Posisi</th><th style="width:24%;">Nominal (Rp)</th><th></th></tr>
                </thead>
                <tbody id="lines-body">
                    @php $oldLines = old('lines', [[], []]); @endphp
                    @foreach ($oldLines as $i => $oldLine)
                        <tr class="line-row">
                            <td>
                                <select name="lines[{{ $i }}][chart_of_account_id]" required class="js-searchable">
                                    <option value="">- pilih akun -</option>
                                    @foreach ($postableAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(($oldLine['chart_of_account_id'] ?? null) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][position]" required>
                                    <option value="">- pilih -</option>
                                    <option value="debit" @selected(($oldLine['position'] ?? null) === 'debit')>Debit</option>
                                    <option value="kredit" @selected(($oldLine['position'] ?? null) === 'kredit')>Kredit</option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0.01" name="lines[{{ $i }}][amount]" value="{{ $oldLine['amount'] ?? '' }}" required></td>
                            <td><button type="button" class="btn-remove-row">Hapus</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <button type="button" class="btn-secondary" id="btn-add-row">+ Tambah Baris</button>

            <div class="balance-bar" id="balance-bar">Total Debit: Rp 0 — Total Kredit: Rp 0</div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">Posting Jurnal Umum</button>
        </form>
    </div>

    <div class="form-card">
        <h3 style="margin-top:0;">Transaksi Jurnal Umum Terbaru</h3>
        @if ($recentEntries->isEmpty())
            <p class="hint">Belum ada transaksi jurnal umum.</p>
        @else
            <table class="data-table">
                <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Cabang</th><th>Total</th><th>Aksi</th></tr></thead>
                <tbody>
                    @foreach ($recentEntries as $entry)
                        <tr>
                            <td>{{ $entry->entry_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>{{ $entry->branch?->name }}</td>
                            <td>Rp {{ number_format((float) $entry->lines->sum('debit'), 0, ',', '.') }}</td>
                            <td><a href="{{ route('admin.jurnal-umum.print', $entry) }}" target="_blank">Cetak</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        (function () {
            var linesBody = document.getElementById('lines-body');
            var addRowBtn = document.getElementById('btn-add-row');
            var balanceBar = document.getElementById('balance-bar');
            var rowTemplate = linesBody.querySelector('.line-row').cloneNode(true);
            var nextIndex = 0;
            linesBody.querySelectorAll('.line-row [name]').forEach(function (el) {
                var match = el.name.match(/^lines\[(\d+)\]/);
                if (match) nextIndex = Math.max(nextIndex, parseInt(match[1], 10) + 1);
            });

            function clearRowValues(row) {
                row.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
                row.querySelectorAll('input').forEach(function (el) { el.value = ''; });
            }

            function reindexRow(row, index) {
                row.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/^lines\[\d+\]/, 'lines[' + index + ']');
                });
            }

            function updateRemoveButtons() {
                var rows = linesBody.querySelectorAll('.line-row');
                rows.forEach(function (row) {
                    row.querySelector('.btn-remove-row').disabled = rows.length <= 2;
                });
            }

            function formatRupiah(value) {
                return 'Rp ' + Math.round(value).toLocaleString('id-ID');
            }

            function updateBalance() {
                var totalDebit = 0;
                var totalKredit = 0;

                linesBody.querySelectorAll('.line-row').forEach(function (row) {
                    var position = row.querySelector('select[name*="[position]"]').value;
                    var amount = parseFloat(row.querySelector('input[name*="[amount]"]').value) || 0;

                    if (position === 'debit') totalDebit += amount;
                    if (position === 'kredit') totalKredit += amount;
                });

                var balanced = Math.round(totalDebit * 100) === Math.round(totalKredit * 100);
                balanceBar.textContent = 'Total Debit: ' + formatRupiah(totalDebit) + ' — Total Kredit: ' + formatRupiah(totalKredit) + (balanced ? ' ✓ Seimbang' : ' — belum seimbang');
                balanceBar.classList.toggle('unbalanced', !balanced);
            }

            addRowBtn.addEventListener('click', function () {
                var newRow = rowTemplate.cloneNode(true);
                clearRowValues(newRow);
                reindexRow(newRow, nextIndex++);
                linesBody.appendChild(newRow);
                updateRemoveButtons();
                // Baris baru dikloning dari template sebelum Dropdown
                // Pencarian sempat memprosesnya (lihat layouts/app.blade.php)
                // — proses manual di sini supaya baris baru bisa dicari juga.
                if (window.enhanceSearchableSelect) {
                    window.enhanceSearchableSelect(newRow.querySelector('select.js-searchable'));
                }
            });

            linesBody.addEventListener('click', function (event) {
                if (event.target.classList.contains('btn-remove-row') && !event.target.disabled) {
                    event.target.closest('.line-row').remove();
                    updateRemoveButtons();
                    updateBalance();
                }
            });

            linesBody.addEventListener('input', updateBalance);
            linesBody.addEventListener('change', updateBalance);

            updateRemoveButtons();
            updateBalance();
        })();
    </script>
@endsection

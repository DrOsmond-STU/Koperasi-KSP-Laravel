@extends('layouts.app')

@section('title', 'Layanan Teller')

@section('content')
    <style>
        .teller-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .feed-item { padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 13px; }
        .feed-item-row { display: flex; justify-content: space-between; align-items: center; }
        .feed-item-cancelled { color: var(--muted); text-decoration: line-through; }
        .btn-cancel-toggle { background: transparent; border: none; color: var(--brick); font-size: 11px; font-weight: 600; cursor: pointer; padding: 0; text-decoration: underline; }
        .cancel-form { display: none; margin-top: 8px; gap: 8px; }
        .cancel-form.open { display: flex; }
        .cancel-form input[type="text"] { flex: 1; padding: 6px 10px; border: 1px solid var(--line); border-radius: 7px; font-size: 12px; }
        .cancel-form button { padding: 6px 12px; background: var(--brick); color: #fff; border: none; border-radius: 7px; font-size: 12px; cursor: pointer; }
        @media (max-width: 980px) { .teller-grid { grid-template-columns: 1fr; } }
        .withdrawal-panel { margin-top: 20px; }
        .decide-form { display: none; margin-top: 8px; gap: 8px; align-items: center; flex-wrap: wrap; }
        .decide-form.open { display: flex; }
        .decide-form input[type="text"] { flex: 1; min-width: 160px; padding: 6px 10px; border: 1px solid var(--line); border-radius: 7px; font-size: 12px; }
        .btn-approve { padding: 6px 12px; background: var(--ok); color: #fff; border: none; border-radius: 7px; font-size: 12px; cursor: pointer; }
        .btn-reject { padding: 6px 12px; background: var(--brick); color: #fff; border: none; border-radius: 7px; font-size: 12px; cursor: pointer; }
    </style>

    <h2>Layanan Teller — Simpanan</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('staf.teller.buka-rekening.create') }}">+ Buka Rekening Baru (anggota belum punya simpanan)</a>
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif

    <div class="teller-grid">
        <div class="panel">
            <h3>Transaksi Simpanan</h3>
            <form method="POST" action="{{ route('staf.teller.preview') }}">
                @csrf
                <div class="field">
                    <label>Rekening</label>
                    <select name="savings_account_id" required class="js-searchable">
                        <option value="">— Pilih Rekening —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->account_number }} — {{ $account->member->name }} ({{ $account->savingsProduct->name }}) — Saldo Rp {{ number_format($account->balance, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Jenis Transaksi</label>
                    <select name="type" required>
                        <option value="setor">Setor</option>
                        <option value="tarik">Tarik</option>
                    </select>
                </div>
                <div class="field">
                    <label>Nominal (Rp)</label>
                    <input type="number" step="0.01" name="amount" required>
                </div>
                <div class="field">
                    <label>Keterangan (opsional)</label>
                    <input type="text" name="description">
                </div>
                <button type="submit" class="btn-primary">Lihat Preview Jurnal</button>
            </form>
        </div>

        <div class="panel">
            <h3>Transaksi Hari Ini</h3>
            @forelse ($recentTransactions as $trx)
                <div class="feed-item">
                    <div class="feed-item-row">
                        <span class="{{ $trx->isCancelled() ? 'feed-item-cancelled' : '' }}">
                            {{ $trx->savingsAccount->member->name }} — {{ ucfirst($trx->type) }}
                        </span>
                        <span class="{{ $trx->isCancelled() ? 'feed-item-cancelled' : '' }}">
                            Rp {{ number_format($trx->amount, 0, ',', '.') }}
                        </span>
                    </div>
                    <a href="{{ route('staf.teller.print-receipt', $trx) }}" target="_blank" style="font-size:11px;">Cetak Bukti</a>
                    @if ($trx->isCancelled())
                        <p class="hint" style="font-size:11px; color:var(--muted); margin: 2px 0 0;">Dibatalkan: {{ $trx->cancellation_reason }}</p>
                    @elseif ($trx->canBeCancelledBy(auth()->user()))
                        <button type="button" class="btn-cancel-toggle" data-toggle-cancel="{{ $trx->id }}">Batalkan</button>
                        <form method="POST" action="{{ route('staf.teller.cancel', $trx) }}" class="cancel-form" id="cancel-form-{{ $trx->id }}">
                            @csrf
                            <input type="text" name="reason" placeholder="Alasan pembatalan" required>
                            <button type="submit">Konfirmasi</button>
                        </form>
                    @endif
                </div>
            @empty
                <p style="color: var(--muted); font-size: 13px;">Belum ada transaksi pada cabang ini hari ini.</p>
            @endforelse
        </div>
    </div>

    @can('simpanan.approve')
        <div class="panel withdrawal-panel">
            <h3>Pengajuan Penarikan dari Anggota</h3>
            @forelse ($pendingWithdrawals as $wr)
                <div class="feed-item">
                    <div class="feed-item-row">
                        <span>{{ $wr->member->name }} — {{ $wr->savingsAccount->account_number }}</span>
                        <span>Rp {{ number_format($wr->amount, 0, ',', '.') }}</span>
                    </div>
                    <button type="button" class="btn-cancel-toggle" data-toggle-decide="{{ $wr->id }}">Proses</button>
                    <a href="{{ route('admin.print.withdrawal-request.show', $wr) }}" target="_blank">Cetak</a>
                    <div class="decide-form" id="decide-form-{{ $wr->id }}">
                        <form method="POST" action="{{ route('staf.teller.decide-withdrawal', $wr) }}" style="display:flex; gap:6px; align-items:center;">
                            @csrf
                            <input type="hidden" name="decision" value="setuju">
                            <button type="submit" class="btn-approve">Setujui</button>
                        </form>
                        <form method="POST" action="{{ route('staf.teller.decide-withdrawal', $wr) }}" style="display:flex; gap:6px; align-items:center; flex:1;">
                            @csrf
                            <input type="hidden" name="decision" value="tolak">
                            <input type="text" name="notes" placeholder="Alasan penolakan" required>
                            <button type="submit" class="btn-reject">Tolak</button>
                        </form>
                    </div>
                </div>
            @empty
                <p style="color: var(--muted); font-size: 13px;">Tidak ada pengajuan penarikan yang menunggu.</p>
            @endforelse
        </div>
    @endcan

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.classList.toggle('open');
            });
        });
        document.querySelectorAll('[data-toggle-decide]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('decide-form-' + btn.dataset.toggleDecide);
                form.classList.toggle('open');
            });
        });
    </script>
@endsection

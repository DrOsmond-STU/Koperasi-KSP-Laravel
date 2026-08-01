@extends('layouts.portal')

@section('title', 'Bayar Angsuran Mandiri')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 480px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .hint { font-size: 11px; color: var(--muted); margin-top: 10px; }
        .notice { background: var(--gold-soft); color: var(--gold-deep); border-radius: 10px; padding: 12px 16px; font-size: 13px; max-width: 480px; }
        .kpi { font-size: 22px; font-weight: 800; color: var(--pine-bright); }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .status-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 10px; }
        .status-tag.menunggu_pembayaran { background: var(--gold-soft); color: var(--gold-deep); }
        .status-tag.dibayar { background: var(--leaf); color: var(--pine-bright); }
        .status-tag.kedaluwarsa, .status-tag.gagal, .status-tag.dibatalkan, .status-tag.perlu_rekonsiliasi_manual { background: var(--brick-soft); color: var(--brick); }
        .empty-note { color: var(--muted); font-size: 13px; }
        .link-back { color: var(--pine-bright); font-size: 13px; text-decoration: none; }
    </style>

    <p><a href="{{ route('portal.loans.schedule', $loan) }}" class="link-back">&larr; Kembali ke Jadwal Angsuran</a></p>
    <h2>Bayar Angsuran Mandiri — {{ $loan->loan_number }}</h2>

    <div class="panel">
        <p style="color:var(--muted); font-size:12px; margin:0 0 6px;">TOTAL TUNGGAKAN SAAT INI</p>
        <p class="kpi">Rp {{ number_format($outstanding, 0, ',', '.') }}</p>
    </div>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($outstanding <= 0)
        <p class="notice">Pinjaman ini sudah lunas — tidak ada tunggakan.</p>
    @elseif ($gatewayConfigured)
        <div class="form-card">
            <form method="POST" action="{{ route('portal.loans.repayment.store', $loan) }}">
                @csrf
                <div class="field">
                    <label>Nominal Bayar (Rp)</label>
                    <input type="number" step="0.01" min="10000" max="{{ $outstanding }}" name="amount" value="{{ old('amount', $outstanding) }}" required>
                </div>
                <button type="submit" class="btn-primary">Bayar via Xendit</button>
                <p class="hint">Boleh membayar sebagian (kurang dari total tunggakan). Anda akan diarahkan ke halaman pembayaran Xendit — status angsuran diperbarui otomatis begitu pembayaran dikonfirmasi.</p>
            </form>
        </div>
    @else
        <p class="notice">Fitur pembayaran mandiri belum aktif — hubungi Admin Sistem.</p>
    @endif

    <h3 style="margin-top:28px;">Riwayat Pembayaran Mandiri</h3>
    <table class="data-table">
        <thead><tr><th>Tanggal</th><th>Nominal</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($history as $rr)
                <tr>
                    <td>{{ $rr->created_at->translatedFormat('d M Y H:i') }}</td>
                    <td>Rp {{ number_format($rr->amount, 0, ',', '.') }}</td>
                    <td><span class="status-tag {{ $rr->status }}">{{ ucfirst(str_replace('_', ' ', $rr->status)) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3"><p class="empty-note">Belum ada pembayaran mandiri.</p></td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

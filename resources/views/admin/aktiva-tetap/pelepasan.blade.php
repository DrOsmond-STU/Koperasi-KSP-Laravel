@extends('layouts.app')

@section('title', 'Pelepasan Aktiva Tetap')

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 560px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .error-text { color: var(--brick); font-size: 12px; margin-top: 4px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-top: 24px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .btn-danger { padding: 5px 10px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 11px; }
    </style>

    <h2>Pelepasan Aktiva Tetap</h2>

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
        <form method="POST" action="{{ route('admin.aktiva-tetap.pelepasan.store') }}">
            @csrf

            <div class="field">
                <label>Aktiva Tetap</label>
                <select name="fixed_asset_id" required class="js-searchable">
                    <option value="">— Pilih Aset —</option>
                    @foreach ($assets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->code }} — {{ $asset->name }} (Nilai Buku: Rp {{ number_format((float) $asset->bookValue(), 0, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Jenis Pelepasan</label>
                <select name="disposal_type" required>
                    <option value="dijual">Dijual</option>
                    <option value="dihapusbukukan">Dihapusbukukan</option>
                    <option value="hilang">Hilang</option>
                </select>
            </div>
            <div class="field">
                <label>Nilai Jual (jika Dijual)</label>
                <input type="number" step="0.01" name="sale_amount">
            </div>

            <button type="submit" class="btn-primary">Proses Pelepasan</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Aset</th><th>Jenis</th><th>Nilai Jual</th><th>Laba/Rugi</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($recentDisposals as $disposal)
                <tr>
                    <td>{{ $disposal->fixedAsset->name }}</td>
                    <td>{{ ucfirst($disposal->disposal_type) }}</td>
                    <td>Rp {{ number_format((float) $disposal->sale_amount, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((float) $disposal->gain_loss_amount, 0, ',', '.') }}</td>
                    <td>
                        @if ($disposal->isCancelled())
                            <span style="color:var(--brick);">Dibatalkan</span>
                        @else
                            Aktif
                        @endif
                    </td>
                    <td>
                        @if (! $disposal->isCancelled() && $disposal->canBeCancelledBy(auth()->user()))
                            <button type="button" class="btn-danger" data-toggle-cancel="disposal-{{ $disposal->id }}">Batalkan</button>
                            <form method="POST" action="{{ route('admin.aktiva-tetap.pelepasan.cancel', $disposal) }}" class="cancel-form" id="cancel-form-disposal-{{ $disposal->id }}" style="display:none; gap:6px; margin-top:6px;">
                                @csrf
                                <input type="text" name="reason" placeholder="Alasan" required style="width:110px; padding:5px 8px; border:1px solid var(--line); border-radius:6px; font-size:11px;">
                                <button type="submit" class="btn-danger">OK</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.aktiva-tetap.pelepasan.print', $disposal) }}" target="_blank">Cetak</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Belum ada pelepasan aktiva tetap.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>
        document.querySelectorAll('[data-toggle-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('cancel-form-' + btn.dataset.toggleCancel);
                form.style.display = form.style.display === 'none' ? 'flex' : 'none';
            });
        });
    </script>
@endsection

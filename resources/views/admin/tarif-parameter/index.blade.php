@extends('layouts.app')

@section('title', 'Tarif & Parameter')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .field-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 10px; }
        .field-row label { display: block; font-size: 11px; font-weight: 600; color: var(--muted); margin-bottom: 4px; }
        .field-row input, .field-row select { padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; width: 140px; }
        .btn-primary { padding: 8px 14px; background: var(--pine); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        .data-table th, .data-table td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 14px; }
        .btn-edit-toggle { background: transparent; border: none; color: var(--pine); font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0; }
        .edit-rate-row .field-row { margin: 0; }
        .edit-rate-row .field-row > div { width: auto; }
    </style>

    <h2>Tarif & Parameter</h2>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <h3>Produk Simpanan</h3>
    @foreach ($savingsProducts as $product)
        <div class="panel">
            <h4>{{ $product->name }} ({{ $product->code }})</h4>

            <form method="POST" action="{{ route('admin.tarif-parameter.savings.update', $product) }}">
                @csrf
                @method('PUT')
                <div class="field-row">
                    <div>
                        <label>Setoran Awal Minimum</label>
                        <input type="number" step="0.01" name="minimum_initial_deposit" value="{{ old('minimum_initial_deposit', $product->minimum_initial_deposit) }}">
                    </div>
                    <div>
                        <label>Setoran Berikutnya Minimum</label>
                        <input type="number" step="0.01" name="minimum_subsequent_deposit" value="{{ old('minimum_subsequent_deposit', $product->minimum_subsequent_deposit) }}">
                    </div>
                    <div>
                        <label>Biaya Admin</label>
                        <input type="number" step="0.01" name="admin_fee" value="{{ old('admin_fee', $product->admin_fee) }}">
                    </div>
                    <div>
                        <label>Periode Biaya Admin</label>
                        <select name="admin_fee_period">
                            <option value="bulanan" {{ $product->admin_fee_period === 'bulanan' ? 'selected' : '' }}>Bulanan</option>
                            <option value="tahunan" {{ $product->admin_fee_period === 'tahunan' ? 'selected' : '' }}>Tahunan</option>
                        </select>
                    </div>
                    <div>
                        <label>Denda Penarikan (%)</label>
                        <input type="number" step="0.01" name="withdrawal_penalty_percentage" value="{{ old('withdrawal_penalty_percentage', $product->withdrawal_penalty_percentage) }}">
                    </div>
                    <button type="submit" class="btn-primary">Simpan Parameter</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.tarif-parameter.savings.rate', $product) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Tarif Baru (%)</label>
                        <input type="number" step="0.001" name="rate_percentage" required>
                    </div>
                    <div>
                        <label>Berlaku Sejak</label>
                        <input type="date" name="effective_from" value="{{ now()->toDateString() }}" required>
                    </div>
                    <button type="submit" class="btn-primary">Tambah Tarif</button>
                </div>
            </form>

            <table class="data-table">
                <thead><tr><th>Berlaku Sejak</th><th>Tarif (%)</th><th></th></tr></thead>
                <tbody>
                    @foreach ($product->rateHistory as $rate)
                        <tr>
                            <td>{{ $rate->effective_from->translatedFormat('d M Y') }}</td>
                            <td>{{ $rate->rate_percentage }}</td>
                            <td><button type="button" class="btn-edit-toggle" data-toggle-edit="savings-rate-{{ $rate->id }}">Ubah</button></td>
                        </tr>
                        <tr class="edit-rate-row" id="edit-row-savings-rate-{{ $rate->id }}" style="display:none;">
                            <td colspan="3">
                                <form method="POST" action="{{ route('admin.tarif-parameter.savings.rate.update', [$product, $rate]) }}" class="field-row">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label>Tarif (%)</label>
                                        <input type="number" step="0.001" name="rate_percentage" value="{{ $rate->rate_percentage }}" required>
                                    </div>
                                    <div>
                                        <label>Berlaku Sejak</label>
                                        <input type="date" name="effective_from" value="{{ $rate->effective_from->toDateString() }}" required>
                                    </div>
                                    <button type="submit" class="btn-primary">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <h3>Produk Pinjaman</h3>
    @foreach ($loanProducts as $product)
        <div class="panel">
            <h4>{{ $product->name }} ({{ $product->code }})</h4>

            <form method="POST" action="{{ route('admin.tarif-parameter.loan.update', $product) }}">
                @csrf
                @method('PUT')
                <div class="field-row">
                    <div>
                        <label>Plafon Minimum</label>
                        <input type="number" step="0.01" name="min_plafon" value="{{ old('min_plafon', $product->min_plafon) }}">
                    </div>
                    <div>
                        <label>Plafon Maksimum</label>
                        <input type="number" step="0.01" name="max_plafon" value="{{ old('max_plafon', $product->max_plafon) }}">
                    </div>
                    <div>
                        <label>Biaya Provisi (%)</label>
                        <input type="number" step="0.01" name="provision_fee_percentage" value="{{ old('provision_fee_percentage', $product->provision_fee_percentage) }}">
                    </div>
                    <div>
                        <label>Denda per Hari (%)</label>
                        <input type="number" step="0.001" name="penalty_percentage_per_day" value="{{ old('penalty_percentage_per_day', $product->penalty_percentage_per_day) }}">
                    </div>
                    <div>
                        <label>Ambang Approval</label>
                        <input type="number" step="0.01" name="approval_threshold" value="{{ old('approval_threshold', $product->approval_threshold) }}">
                    </div>
                    <button type="submit" class="btn-primary">Simpan Parameter</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.tarif-parameter.loan.rate', $product) }}">
                @csrf
                <div class="field-row">
                    <div>
                        <label>Tarif Baru (%)</label>
                        <input type="number" step="0.001" name="rate_percentage" required>
                    </div>
                    <div>
                        <label>Berlaku Sejak</label>
                        <input type="date" name="effective_from" value="{{ now()->toDateString() }}" required>
                    </div>
                    <button type="submit" class="btn-primary">Tambah Tarif</button>
                </div>
            </form>

            <table class="data-table">
                <thead><tr><th>Berlaku Sejak</th><th>Tarif (%)</th><th></th></tr></thead>
                <tbody>
                    @foreach ($product->rateHistory as $rate)
                        <tr>
                            <td>{{ $rate->effective_from->translatedFormat('d M Y') }}</td>
                            <td>{{ $rate->rate_percentage }}</td>
                            <td><button type="button" class="btn-edit-toggle" data-toggle-edit="loan-rate-{{ $rate->id }}">Ubah</button></td>
                        </tr>
                        <tr class="edit-rate-row" id="edit-row-loan-rate-{{ $rate->id }}" style="display:none;">
                            <td colspan="3">
                                <form method="POST" action="{{ route('admin.tarif-parameter.loan.rate.update', [$product, $rate]) }}" class="field-row">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label>Tarif (%)</label>
                                        <input type="number" step="0.001" name="rate_percentage" value="{{ $rate->rate_percentage }}" required>
                                    </div>
                                    <div>
                                        <label>Berlaku Sejak</label>
                                        <input type="date" name="effective_from" value="{{ $rate->effective_from->toDateString() }}" required>
                                    </div>
                                    <button type="submit" class="btn-primary">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <script>
        document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var row = document.getElementById('edit-row-' + btn.dataset.toggleEdit);
                row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
            });
        });
    </script>
@endsection

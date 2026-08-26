@extends('layouts.app')

@section('title', 'Catat Angsuran')

@section('content')
    <style>
        .angsuran-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 12px; }
        .feed-item { padding: 8px 0; border-bottom: 1px solid var(--line); font-size: 13px; }
        .feed-item-row { display: flex; justify-content: space-between; align-items: center; }
        @media (max-width: 980px) { .angsuran-grid { grid-template-columns: 1fr; } }
    </style>

    <h2>Catat Angsuran</h2>
    <p style="color: var(--muted); margin-top: -8px;">Pembayaran angsuran tunai yang diterima di loket — langsung tercatat, bukan lewat gateway pembayaran mandiri.</p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="angsuran-grid">
        <div class="panel">
            <h3>Pembayaran Angsuran</h3>
            <form method="POST" action="{{ route('staf.angsuran.preview') }}">
                @csrf
                <div class="field">
                    <label>Tanggal Bayar</label>
                    <input type="date" name="paid_at" value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    <p style="color: var(--muted); font-size: 11px; margin: 6px 0 0;">
                        Ganti tanggal ini kalau sedang menyusulkan angsuran lama yang belum sempat dicatat —
                        jangan biarkan bertanggal hari ini kalau pembayarannya terjadi di hari lain.
                    </p>
                </div>
                <div class="field">
                    <label>Pinjaman</label>
                    <select name="loan_id" required class="js-searchable">
                        <option value="">— Pilih Pinjaman —</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}" @selected(old('loan_id', $preselectedLoanId) == $loan->id)>
                                {{ $loan->loan_number }} — {{ $loan->member->name }} ({{ $loan->loanProduct->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Saldo Outstanding (Rp)</label>
                    <input type="text" id="outstanding-balance-display" value="—" readonly style="background: var(--muted-bg, #f2f2f2); color: var(--muted);">
                    <p style="color: var(--muted); font-size: 11px; margin: 6px 0 0;">
                        Sisa pokok pinjaman anggota ini saat ini (sebelum pembayaran ini dicatat) — informasi saja, tidak ikut dikirim.
                    </p>
                </div>
                <div class="field">
                    <label>Nominal Bayar (Rp)</label>
                    <input type="number" step="0.01" min="0" name="nominal_bayar" id="nominal-bayar-input" value="{{ old('nominal_bayar') }}">
                    <p style="color: var(--muted); font-size: 11px; margin: 6px 0 0;">
                        Ketik jumlah yang diterima — Pokok/Jasa/Denda di bawah otomatis mengikuti (jasa sesuai pengaturan di Master Produk Pinjaman).
                    </p>
                </div>
                <div class="field">
                    <label>Angsuran Pokok (Rp)</label>
                    <input type="number" step="0.01" min="0" name="principal_portion" id="principal-portion-input" value="{{ old('principal_portion', 0) }}" required>
                </div>
                <div class="field">
                    <label>Jasa (Rp)</label>
                    <input type="number" step="0.01" min="0" name="interest_portion" id="interest-portion-input" value="{{ old('interest_portion', 0) }}" required>
                </div>
                <div class="field">
                    <label>Denda (Rp)</label>
                    <input type="number" step="0.01" min="0" name="penalty_portion" id="penalty-portion-input" value="{{ old('penalty_portion', 0) }}" required>
                </div>
                <p style="color: var(--muted); font-size: 11px; margin: -6px 0 14px;">
                    Jasa terisi otomatis (tarif NORMAL dari produk pinjaman, tidak mengejar tunggakan) begitu Pinjaman dipilih; Pokok = Nominal Bayar − Jasa − Denda,
                    dihitung ulang tiap Nominal Bayar/Jasa/Denda diubah. Ketiganya tetap bisa diedit manual sebelum disimpan.
                </p>
                <div class="field">
                    <label>Akun Kas Penerima</label>
                    <select name="cash_account_id" required>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('cash_account_id', $defaultCashAccountId) === $account->id)>
                                {{ $account->code }} — {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                    <p style="color: var(--muted); font-size: 11px; margin: 6px 0 0;">
                        Default kas cabang USP — ganti kalau uang benar-benar diterima di kas cabang lain.
                    </p>
                </div>
                <div class="field">
                    <label>Keterangan (opsional)</label>
                    <input type="text" name="description" value="{{ old('description') }}">
                </div>
                <button type="submit" class="btn-primary">Lihat Rincian Alokasi</button>
            </form>
        </div>

        <div class="panel">
            <h3>Angsuran Hari Ini</h3>
            @forelse ($recentRepayments as $repayment)
                <div class="feed-item">
                    <div class="feed-item-row">
                        <span>{{ $repayment->loan->member->name }} — {{ $repayment->loan->loan_number }}</span>
                        <span>Rp {{ number_format($repayment->amount, 0, ',', '.') }}</span>
                    </div>
                    <a href="{{ route('admin.print.loan-repayment.show', $repayment) }}" target="_blank" style="font-size:11px;">Cetak Bukti</a>
                </div>
            @empty
                <p style="color: var(--muted); font-size: 13px;">Belum ada angsuran dicatat hari ini.</p>
            @endforelse
        </div>
    </div>

    <script>
        (function () {
            // Jasa NORMAL per pinjaman (tarif dari Master Produk Pinjaman,
            // TIDAK mengejar tunggakan) — lihat LoanRepaymentService::
            // normalInstallment(). Jasa-nya TETAP (flat per periode), bukan
            // proporsional terhadap Nominal Bayar — sama seperti pola
            // histori: jasa selalu sama persis tiap pembayaran, berapa pun
            // nominalnya, dan Pokok-lah yang menyesuaikan.
            var normalInstallments = @json($normalInstallments);
            // Saldo outstanding (sisa pokok) per pinjaman — lihat
            // LoanRepaymentService::outstandingPrincipal(). Murni tampilan,
            // tidak pernah dikirim ke server (input-nya readonly, tanpa name).
            var outstandingBalances = @json($outstandingBalances);

            var loanSelect = document.querySelector('select[name="loan_id"]');
            var outstandingDisplay = document.getElementById('outstanding-balance-display');
            var nominalInput = document.getElementById('nominal-bayar-input');
            var principalInput = document.getElementById('principal-portion-input');
            var interestInput = document.getElementById('interest-portion-input');
            var penaltyInput = document.getElementById('penalty-portion-input');

            function formatRupiah(amount) {
                return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function recomputePokok() {
                var nominal = parseFloat(nominalInput.value) || 0;
                var jasa = parseFloat(interestInput.value) || 0;
                var denda = parseFloat(penaltyInput.value) || 0;
                principalInput.value = Math.max(0, nominal - jasa - denda).toFixed(2);
            }

            function applyNormalDefaults() {
                var normal = normalInstallments[loanSelect.value];
                interestInput.value = normal ? normal.interest : 0;
                penaltyInput.value = 0;
                nominalInput.value = '';
                principalInput.value = 0;

                var outstanding = outstandingBalances[loanSelect.value];
                outstandingDisplay.value = loanSelect.value && outstanding !== undefined ? formatRupiah(outstanding) : '—';
            }

            loanSelect.addEventListener('change', applyNormalDefaults);
            // Nominal Bayar/Jasa/Denda menentukan Pokok. Pokok sendiri tetap
            // bisa diketik manual (mis. staf menyesuaikan hasil bulat) tanpa
            // memicu hitung ulang terhadap dirinya sendiri.
            [nominalInput, interestInput, penaltyInput].forEach(function (input) {
                input.addEventListener('input', recomputePokok);
            });

            // Setelah redirect balik dari error validasi, angka yang sudah
            // diketik staf (old()) dipertahankan — jangan ditimpa default.
            var hasOldInput = {{ Js::from(old('principal_portion') !== null) }};
            if (loanSelect.value && !hasOldInput) {
                applyNormalDefaults();
            }
        })();
    </script>
@endsection

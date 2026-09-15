@extends('layouts.app')

@section('title', 'Perbaikan Jadwal Angsuran')

@section('content')
    <style>
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
        .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
        .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 13px; }
        .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
        .data-table td.num, .data-table th.num { text-align: right; }
        .old-zero { color: var(--brick); font-weight: 700; }
        .new-amount { color: var(--pine-ink); font-weight: 700; }
        .warn-note { color: var(--brick); font-size: 11px; margin: 4px 0 0; }
        .empty-note { color: var(--muted); font-size: 13px; }
        .ok-note { color: var(--ok); font-size: 14px; font-weight: 600; }
        .btn-save { padding: 8px 16px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .select-all-row { margin-bottom: 10px; font-size: 13px; }
    </style>

    <h2>Perbaikan Jadwal Angsuran</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 720px;">
        Mendeteksi pinjaman aktif (status "dicairkan") yang tidak punya baris jadwal angsuran
        (<code>loan_schedules</code>) sama sekali — gejalanya: Saldo Outstanding tampil Rp 0 di
        Catat Angsuran padahal belum lunas, dan Bayar Angsuran Mandiri dari Portal anggota selalu
        ditolak. Jadwal dibangun ulang dari data pinjaman itu sendiri (pokok/tenor/tarif) lalu
        dicocokkan dengan riwayat pembayaran yang sudah tercatat — tidak ada angka yang dikarang.
        Hanya menambah baris <code>loan_schedules</code> baru, tidak mengubah riwayat pembayaran
        atau jurnal yang sudah ada.
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif

    <div class="panel">
        @if ($loans->isEmpty())
            <p class="ok-note">✓ Tidak ada pinjaman aktif yang jadwalnya kosong saat ini.</p>
        @else
            <p class="error-msg" style="margin-bottom: 16px;">
                Ditemukan {{ $loans->count() }} pinjaman aktif tanpa jadwal angsuran.
            </p>

            <form method="POST" action="{{ route('admin.pinjaman.perbaikan-jadwal.store') }}">
                @csrf
                <p class="select-all-row">
                    <label><input type="checkbox" id="select-all"> Pilih semua</label>
                </p>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>No. Pinjaman</th>
                            <th>Anggota</th>
                            <th>Produk</th>
                            <th class="num">Pokok Pinjaman</th>
                            <th class="num">Outstanding Saat Ini</th>
                            <th class="num">Outstanding Setelah Diperbaiki</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($loans as $loan)
                            @php $preview = $previews[$loan->id]; @endphp
                            <tr>
                                <td><input type="checkbox" name="loan_ids[]" value="{{ $loan->id }}" class="loan-checkbox"></td>
                                <td>{{ $loan->loan_number }}</td>
                                <td>{{ $loan->member->name }}</td>
                                <td>{{ $loan->loanProduct->name }}</td>
                                <td class="num">Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                                <td class="num old-zero">Rp 0</td>
                                <td class="num new-amount">
                                    Rp {{ number_format($preview['new_outstanding'], 0, ',', '.') }}
                                    @foreach ($preview['warnings'] as $warning)
                                        <p class="warn-note">⚠ {{ $warning }}</p>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="submit" class="btn-save"
                        onclick="return confirm('Jalankan perbaikan untuk pinjaman yang dicentang? Baris loan_schedules baru akan ditulis untuk masing-masing.');">
                    Jalankan Perbaikan untuk yang Dicentang
                </button>
            </form>
        @endif
    </div>

    <script>
        var selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.loan-checkbox').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
            });
        }
    </script>
@endsection

@extends('layouts.app')

@section('title', 'Pratinjau Koreksi Saldo Awal Neraca')

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')
    @php $rp = fn ($n) => number_format((float) $n, 2, ',', '.'); @endphp

    <p><a href="{{ route('admin.saldo-awal.koreksi-neraca', $batch) }}" class="tautan-kembali">← Kembali ke formulir</a></p>

    <h2>Pratinjau Koreksi Saldo Awal Neraca</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 860px;">
        Batch #{{ $batch->id }} · {{ $batch->branch->name ?? '-' }}. Jurnal koreksi di bawah akan diposting bertanggal
        <strong>{{ $batch->cutoff_date->translatedFormat('d M Y') }}</strong> (tanggal cut-off). Belum ada yang disimpan.
    </p>

    <div class="panel" style="overflow-x:auto;">
        <h3 style="margin-top:0;">Jurnal koreksi</h3>
        <table class="data-table">
            <thead>
                <tr><th>Kode</th><th>Nama akun</th><th class="num">Saldo awal sekarang</th><th class="num">Menjadi</th><th class="num">Debit</th><th class="num">Kredit</th></tr>
            </thead>
            <tbody>
                @foreach ($rencana['baris'] as $b)
                    <tr>
                        <td>{{ $b['account']->code }}</td>
                        <td>{{ $b['account']->name }} @if ($b['penyeimbang'])<span class="badge badge-aktif">penyeimbang</span>@endif</td>
                        <td class="num">{{ $rp($b['sekarang']) }}</td>
                        <td class="num"><strong>{{ $rp($b['menjadi']) }}</strong></td>
                        <td class="num">{{ $b['debit'] ? $rp($b['debit']) : '' }}</td>
                        <td class="num">{{ $b['kredit'] ? $rp($b['kredit']) : '' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4"><strong>Total</strong></td>
                    <td class="num"><strong>{{ $rp($rencana['total_debit']) }}</strong></td>
                    <td class="num"><strong>{{ $rp($rencana['total_kredit']) }}</strong></td>
                </tr>
            </tbody>
        </table>
        @if (abs($rencana['selisih']) >= 0.005)
            <p class="error-msg">
                Koreksi belum seimbang: selisih debit − kredit Rp {{ $rp($rencana['selisih']) }}. Kembali ke formulir, lalu pilih
                akun penyeimbang atau sesuaikan angkanya. Koreksi yang tidak seimbang tidak bisa disimpan.
            </p>
        @endif
    </div>

    <div class="panel">
        <h3 style="margin-top:0;">Neraca saldo awal</h3>
        @include('admin.saldo-awal.partials.koreksi-neraca-ringkasan', ['kolom' => ['Sekarang' => $rencana['sebelum'], 'Sesudah koreksi' => $rencana['sesudah']]])
    </div>

    @if (abs($rencana['selisih']) < 0.005)
        <form method="POST" action="{{ route('admin.saldo-awal.koreksi-neraca.simpan', $batch) }}" class="panel"
              onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            @foreach ($target as $id => $nilai)
                <input type="hidden" name="target[{{ $id }}]" value="{{ $nilai }}">
            @endforeach
            <input type="hidden" name="penyeimbang" value="{{ $penyeimbang }}">
            <input type="hidden" name="alasan" value="{{ $alasan }}">
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
            <p style="margin-top:0;">Alasan: <strong>{{ $alasan }}</strong></p>
            <div class="konfirmasi">
                <input type="checkbox" name="konfirmasi" value="1" id="konfirmasi" required>
                <label for="konfirmasi" style="font-weight:400;">
                    Saya sudah memeriksa pratinjau di atas. Posting jurnal koreksi saldo awal sebesar Rp {{ $rp($rencana['total_debit']) }}
                    bertanggal {{ $batch->cutoff_date->translatedFormat('d M Y') }}. Saldo awal migrasi dan jurnal lain tidak diubah.
                </label>
            </div>
            <label style="font-size:13px;">Kata sandi Anda<br>
                <input type="password" name="password" required autocomplete="current-password" style="padding:7px 9px; min-width:240px;">
            </label>
            <p><button type="submit" class="btn-save">Simpan Koreksi</button></p>
        </form>
    @endif
@endsection

@extends('layouts.app')

@section('title', 'Koreksi Angsuran #'.$r->id)

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $nilai = fn (string $k, $asal) => old($k, $data[$k] ?? $asal);
        $tglAsal = ($r->transaction_date ?? $r->paid_at)?->toDateString();
    @endphp

    <p><a href="{{ route('admin.pinjaman.perbaikan-saldo.show', ['loan' => $loan, 'per' => request('per')]) }}" class="tautan-kembali">← Kembali ke kartu {{ $loan->loan_number }}</a></p>

    <h2>Koreksi Angsuran #{{ $r->id }}</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 820px;">
        {{ $loan->loan_number }} · {{ $loan->member->name ?? '-' }}. Menyimpan koreksi akan: (1) menandai angsuran #{{ $r->id }}
        <em>dibatalkan</em> dan membalik jurnalnya (jurnal pembalik bertanggal sama dengan jurnal asal), (2) mencatat angsuran
        pengganti dengan nilai di bawah beserta jurnalnya pada tanggal transaksi yang benar (akun kas penerima sama dengan
        jurnal asal), (3) menyelaraskan jadwal & status lunas pinjaman dengan buku besar. Semuanya tercatat di jejak audit.
    </p>

    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <p class="error-msg">{{ $errors->first() }}</p>
    @endif
    @if ($galat)
        <p class="error-msg">Koreksi ini tidak bisa diterapkan: {{ $galat }}</p>
    @endif

    <div class="panel">
        <h3 style="margin-top:0;">Angsuran yang akan dikoreksi</h3>
        <table class="data-table ringkas">
            <tbody>
                <tr><td>Tanggal transaksi</td><td class="num">{{ \Illuminate\Support\Carbon::parse($tglAsal)->translatedFormat('d M Y') }}</td><td>Diinput</td><td class="num">{{ $r->created_at->translatedFormat('d M Y H:i') }} oleh {{ $r->createdBy->name ?? '-' }}</td></tr>
                <tr><td>Jumlah</td><td class="num">{{ $rp($r->amount) }}</td><td>Jurnal</td><td class="num">#{{ $r->journal_entry_id }}</td></tr>
                <tr><td>Pokok</td><td class="num">{{ $rp($r->principal_portion) }}</td><td>Jasa</td><td class="num">{{ $rp($r->interest_portion) }}</td></tr>
                <tr><td>Denda</td><td class="num">{{ $rp($r->penalty_portion) }}</td><td>Keterangan</td><td>{{ $r->description ?: '—' }}</td></tr>
            </tbody>
        </table>
    </div>

    <form method="GET" action="{{ route('admin.pinjaman.perbaikan-saldo.koreksi', $r) }}" id="form-koreksi" class="panel">
        <h3 style="margin-top:0;">Nilai yang benar</h3>
        <input type="hidden" name="per" value="{{ request('per') }}">
        <input type="hidden" name="pratinjau" value="1">
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap:12px; max-width:820px;">
            <label style="font-size:13px;">Tanggal transaksi<br>
                <input type="date" name="tanggal" value="{{ $nilai('tanggal', $tglAsal) }}" max="{{ now()->toDateString() }}" required style="padding:7px 9px; width:100%;"></label>
            <label style="font-size:13px;">Pokok (Rp)<br>
                <input type="number" step="0.01" min="0" name="pokok" value="{{ $nilai('pokok', (float) $r->principal_portion) }}" required style="padding:7px 9px; width:100%;"></label>
            <label style="font-size:13px;">Jasa (Rp)<br>
                <input type="number" step="0.01" min="0" name="jasa" value="{{ $nilai('jasa', (float) $r->interest_portion) }}" required style="padding:7px 9px; width:100%;"></label>
            <label style="font-size:13px;">Denda (Rp)<br>
                <input type="number" step="0.01" min="0" name="denda" value="{{ $nilai('denda', (float) $r->penalty_portion) }}" style="padding:7px 9px; width:100%;"></label>
        </div>
        <label style="font-size:13px; display:block; margin-top:12px; max-width:820px;">Alasan koreksi (wajib, tercatat di jejak audit)<br>
            <input type="text" name="alasan" maxlength="200" value="{{ $nilai('alasan', '') }}" required placeholder="mis. salah tanggal: uang diterima 2 Okt 2026" style="padding:7px 9px; width:100%;"></label>
        <p class="tenang" style="margin-top:8px; max-width:820px;">
            Bila transaksi ini <strong>seharusnya tidak ada</strong> (mis. input ganda), isi pokok, jasa dan denda = 0:
            angsuran dibatalkan dan jurnalnya dibalik tanpa angsuran pengganti.
        </p>
        <p style="margin-top:12px;">
            <button type="submit" class="btn-save">Pratinjau Hasil Koreksi</button>
            <span class="tenang" style="margin-left:8px;">Pratinjau tidak menyimpan apa pun.</span>
        </p>
    </form>

    @if ($data !== null && $pratinjau !== null)
        @php
            $selisihJumlah = round(($data['pokok'] + $data['jasa'] + $data['denda']) - (float) $r->amount, 2);
            $tanpaPengganti = ($data['pokok'] + $data['jasa'] + $data['denda']) < 0.005;
            $j = $pratinjau['jadwal'];
        @endphp
        <div class="panel" style="overflow-x:auto;">
            <h3 style="margin-top:0;">Pratinjau sesudah koreksi</h3>
            <table class="data-table ringkas">
                <thead><tr><th></th><th class="num">Sekarang</th><th class="num">Sesudah koreksi</th></tr></thead>
                <tbody>
                    <tr><td>Sisa pokok per {{ $pratinjau['per']->translatedFormat('d M Y') }}</td><td class="num">{{ $rp($sekarang['saldo_per']['pokok']) }}</td><td class="num"><strong>{{ $rp($pratinjau['saldo_per']['pokok']) }}</strong></td></tr>
                    <tr><td>Sisa jasa per {{ $pratinjau['per']->translatedFormat('d M Y') }}</td><td class="num">{{ $rp($sekarang['saldo_per']['jasa']) }}</td><td class="num"><strong>{{ $rp($pratinjau['saldo_per']['jasa']) }}</strong></td></tr>
                    <tr><td>Sisa pokok akhir</td><td class="num">{{ $rp($sekarang['sisa_pokok']) }}</td><td class="num"><strong>{{ $rp($pratinjau['sisa_pokok']) }}</strong></td></tr>
                    <tr><td>Sisa jasa akhir</td><td class="num">{{ $rp($sekarang['sisa_jasa']) }}</td><td class="num"><strong>{{ $rp($pratinjau['sisa_jasa']) }}</strong></td></tr>
                    <tr><td>Status pinjaman</td><td class="num">{{ ucfirst($sekarang['loan']->status) }}</td><td class="num"><strong>{{ ucfirst($pratinjau['loan']->status) }}</strong></td></tr>
                </tbody>
            </table>
            @if ($tanpaPengganti)
                <p class="warn-note" style="font-size:12.5px;">
                    ⚠ Angsuran #{{ $r->id }} akan dibatalkan tanpa pengganti: kas penerima berkurang {{ $rp($r->amount) }} (jurnal asalnya dibalik).
                    Pastikan uang ini memang tidak pernah diterima dari anggota.
                </p>
            @elseif (abs($selisihJumlah) > 0.005)
                <p class="warn-note" style="font-size:12.5px;">
                    ⚠ Jumlah uang angsuran pengganti ({{ $rp($data['pokok'] + $data['jasa'] + $data['denda']) }}) berbeda dari angsuran asal ({{ $rp($r->amount) }}):
                    kas penerima ikut {{ $selisihJumlah > 0 ? 'bertambah' : 'berkurang' }} {{ $rp(abs($selisihJumlah)) }}. Pastikan memang begitu uang yang diterima.
                </p>
            @endif
            @foreach ($pratinjau['masalah'] as $m)
                <p class="warn-note" style="font-size:12.5px;">⚠ Sesudah koreksi: {{ $m }}</p>
            @endforeach

            @include('admin.pinjaman.partials.perbaikan-saldo-tabel', ['k' => $pratinjau, 'aksi' => false, 'pengguna' => $pengguna,
                'sorot' => collect($pratinjau['baris'])->filter(fn ($b) => ! $b['batal'] && str_starts_with((string) $b['repayment']->description, 'Pengganti angsuran #'.$r->id.' '))->map(fn ($b) => $b['repayment']->id)->values()->all()])

            <form method="POST" action="{{ route('admin.pinjaman.perbaikan-saldo.simpan', $r) }}" style="margin-top:14px;">
                @csrf
                @foreach (['tanggal', 'pokok', 'jasa', 'denda', 'alasan'] as $f)
                    <input type="hidden" name="{{ $f }}" value="{{ $data[$f] }}">
                @endforeach
                <input type="hidden" name="per" value="{{ request('per') }}">
                <div class="konfirmasi">
                    <input type="checkbox" name="konfirmasi" value="1" id="konfirmasi" required>
                    <label for="konfirmasi" style="font-weight:400;">
                        @if ($tanpaPengganti)
                            Saya sudah memeriksa pratinjau di atas. Angsuran #{{ $r->id }} ({{ $rp($r->amount) }}) seharusnya tidak ada:
                            dibatalkan dan jurnalnya dibalik <strong>tanpa angsuran pengganti</strong>.
                        @else
                            Saya sudah memeriksa pratinjau di atas. Angsuran #{{ $r->id }} dibatalkan (jurnal dibalik) dan diganti angsuran baru
                            bertanggal {{ \Illuminate\Support\Carbon::parse($data['tanggal'])->translatedFormat('d M Y') }}: pokok {{ $rp($data['pokok']) }},
                            jasa {{ $rp($data['jasa']) }}, denda {{ $rp($data['denda']) }}.
                        @endif
                    </label>
                </div>
                <button type="submit" class="btn-save" onclick="return confirm('{{ $tanpaPengganti ? 'Batalkan angsuran #'.$r->id.' tanpa pengganti?' : 'Simpan koreksi angsuran #'.$r->id.'?' }}');">{{ $tanpaPengganti ? 'Batalkan Angsuran' : 'Simpan Koreksi' }}</button>
            </form>
        </div>
    @endif

    <div class="panel" style="overflow-x:auto;">
        <h3 style="margin-top:0;">Kartu sekarang</h3>
        @include('admin.pinjaman.partials.perbaikan-saldo-tabel', ['k' => $sekarang, 'aksi' => false, 'pengguna' => $pengguna, 'sorot' => [$r->id]])
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Koreksi Saldo Awal Neraca')

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')
    <style>
        .isian-saldo { width: 170px; padding: 6px 8px; text-align: right; font-variant-numeric: tabular-nums; }
        .isian-saldo.diubah { border-color: var(--pine); background: rgba(17, 84, 59, 0.06); }
        tr.ada-koreksi td { background: rgba(255, 191, 0, 0.06); }
        #tabel-akun.hanya-bersaldo tr.tanpa-saldo { display: none; }
    </style>

    @php $rp = fn ($n) => number_format((float) $n, 2, ',', '.'); @endphp

    <h2>Koreksi Saldo Awal Neraca</h2>
    <p style="color: var(--muted); font-size: 13px; margin-top: -8px; max-width: 860px;">
        Batch #{{ $batch->id }} · {{ $batch->branch->name ?? '-' }} · cut-off <strong>{{ $batch->cutoff_date->translatedFormat('d M Y') }}</strong>.
        Isi <em>saldo awal yang benar</em> pada akun yang salah. Sistem menghitung selisihnya lalu memposting
        <strong>satu jurnal koreksi baru</strong> bertanggal cut-off. Saldo awal hasil migrasi, jurnal pembukaan dan transaksi lain
        <strong>tidak diubah, tidak ditambah, tidak dihapus</strong>. Koreksi bisa dibatalkan (jurnalnya dibalik).
    </p>

    @if (session('status'))
        <p class="status-msg">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="error-msg">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <p class="error-msg">{{ $errors->first() }}</p>
    @endif

    <div class="panel">
        <h3 style="margin-top:0;">Neraca saldo awal sekarang</h3>
        @include('admin.saldo-awal.partials.koreksi-neraca-ringkasan', ['kolom' => ['Sekarang' => $posisi['ringkasan']]])
        <p class="info-note">Saldo awal sekarang = saldo awal migrasi + koreksi yang masih berlaku.</p>
    </div>

    <form method="POST" action="{{ route('admin.saldo-awal.koreksi-neraca.pratinjau', $batch) }}" class="panel" style="overflow-x:auto;">
        @csrf
        <h3 style="margin-top:0;">Saldo awal per akun</h3>
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:10px;">
            <input type="text" id="cari-akun" placeholder="Cari kode / nama akun" style="min-width:260px; padding:7px 9px;">
            <label style="font-size:13px;"><input type="checkbox" id="hanya-bersaldo" checked> Hanya akun yang bersaldo / sedang diisi</label>
            <span class="tenang" id="jumlah-diubah"></span>
        </div>
        <p class="tenang" style="margin-top:0;">
            Angka mengikuti <strong>saldo normal</strong> akun: Aset & Beban di debit, Kewajiban, Ekuitas & Pendapatan di kredit
            (angka minus = saldo di sisi sebaliknya). Format: <code>1.250.000</code> atau <code>1250000,50</code>. Kosongkan bila tidak diubah.
        </p>

        <table class="data-table" id="tabel-akun">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama akun</th><th>Tipe</th><th>Normal</th>
                    <th class="num">Saldo awal migrasi</th><th class="num">Koreksi berlaku</th><th class="num">Saldo awal sekarang</th>
                    <th class="num">Saldo awal yang benar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posisi['baris'] as $b)
                    @php
                        $a = $b['account'];
                        $isi = old('target.'.$a->id);
                        $bersaldo = abs($b['sekarang']) >= 0.005 || abs($b['asal']) >= 0.005 || abs($b['koreksi']) >= 0.005;
                    @endphp
                    <tr class="{{ $bersaldo || filled($isi) ? '' : 'tanpa-saldo' }} {{ abs($b['koreksi']) >= 0.005 ? 'ada-koreksi' : '' }}"
                        data-cari="{{ strtolower($a->code.' '.$a->name) }}">
                        <td>{{ $a->code }}</td>
                        <td>{{ $a->name }}</td>
                        <td>{{ $a->statement === 'NERACA' ? ucfirst(strtolower($a->type)) : 'Laba/Rugi · '.ucfirst(strtolower($a->type)) }}</td>
                        <td>{{ $a->normal_balance === 'DEBIT' ? 'Debit' : 'Kredit' }}</td>
                        <td class="num">{{ $rp($b['asal']) }}</td>
                        <td class="num">{{ abs($b['koreksi']) >= 0.005 ? $rp($b['koreksi']) : '—' }}</td>
                        <td class="num"><strong>{{ $rp($b['sekarang']) }}</strong></td>
                        <td class="num">
                            @if ($b['bisa_diubah'])
                                <input type="text" inputmode="decimal" class="isian-saldo" name="target[{{ $a->id }}]"
                                       value="{{ $isi }}" placeholder="{{ $rp($b['sekarang']) }}" autocomplete="off">
                            @else
                                <span class="tenang">{{ $a->code === $kodeShu ? 'dihitung dari akun L/R' : 'bukan akun postable' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:12px; max-width:900px; margin-top:14px;">
            <label style="font-size:13px;">Akun penyeimbang (opsional)<br>
                <select name="penyeimbang" style="padding:7px 9px; width:100%;">
                    <option value="">— tanpa penyeimbang (koreksi harus seimbang sendiri) —</option>
                    @foreach ($posisi['baris']->where('bisa_diubah', true)->filter(fn ($b) => $b['account']->statement === 'NERACA') as $b)
                        <option value="{{ $b['account']->id }}" @selected((int) old('penyeimbang') === $b['account']->id)>{{ $b['account']->code }} — {{ $b['account']->name }}</option>
                    @endforeach
                </select>
                <span class="tenang">Sisa selisih debit/kredit otomatis dibukukan ke akun ini (mis. akun ekuitas/selisih saldo awal).</span>
            </label>
            <label style="font-size:13px;">Alasan koreksi (wajib, tercatat di jejak audit)<br>
                <input type="text" name="alasan" maxlength="200" value="{{ old('alasan') }}" required placeholder="mis. menyesuaikan neraca SMIK per 31 Jul 2026" style="padding:7px 9px; width:100%;">
            </label>
        </div>
        <p style="margin-top:14px;">
            <button type="submit" class="btn-save">Pratinjau Koreksi</button>
            <span class="tenang" style="margin-left:8px;">Pratinjau tidak menyimpan apa pun.</span>
        </p>
    </form>

    <div class="panel" style="overflow-x:auto;">
        <h3 style="margin-top:0;">Riwayat koreksi</h3>
        @forelse ($riwayat as $k)
            <div style="border-top:1px solid var(--line); padding-top:10px; margin-top:10px;">
                <p style="margin:0 0 6px;">
                    <strong>Koreksi #{{ $k->id }}</strong> · jurnal #{{ $k->journal_entry_id }} ·
                    {{ $k->created_at->translatedFormat('d M Y H:i') }} oleh {{ $k->createdBy->name ?? '-' }} · {{ $k->reason }}
                    @if ($k->isCancelled())
                        <br><span class="warn-note" style="font-size:12px;">Dibatalkan {{ $k->cancelled_at->translatedFormat('d M Y H:i') }} oleh {{ $k->cancelledBy->name ?? '-' }}
                            (jurnal pembalik #{{ $k->reversal_journal_entry_id }}): {{ $k->cancellation_reason }}</span>
                    @endif
                </p>
                <table class="data-table ringkas" @if ($k->isCancelled()) style="opacity:.55;" @endif>
                    <thead><tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead>
                    <tbody>
                        @foreach ($k->journalEntry?->lines ?? [] as $l)
                            <tr><td>{{ $l->account->code ?? '' }} {{ $l->account->name ?? '' }}</td>
                                <td class="num">{{ (float) $l->debit ? $rp($l->debit) : '' }}</td>
                                <td class="num">{{ (float) $l->credit ? $rp($l->credit) : '' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                @unless ($k->isCancelled())
                    <form method="POST" action="{{ route('admin.saldo-awal.koreksi-neraca.batal', [$batch, $k]) }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;"
                          onsubmit="return confirm('Batalkan koreksi #{{ $k->id }}? Jurnalnya akan dibalik.');">
                        @csrf
                        <input type="text" name="alasan_batal" maxlength="200" required placeholder="Alasan pembatalan" style="padding:6px 8px; min-width:240px;">
                        <input type="password" name="password" required placeholder="Kata sandi Anda" autocomplete="current-password" style="padding:6px 8px;">
                        <button type="submit" class="btn-danger">Batalkan koreksi</button>
                    </form>
                @endunless
            </div>
        @empty
            <p class="tenang">Belum ada koreksi untuk batch ini.</p>
        @endforelse
    </div>

    <script>
        (function () {
            const tabel = document.getElementById('tabel-akun');
            const cari = document.getElementById('cari-akun');
            const hanya = document.getElementById('hanya-bersaldo');
            const info = document.getElementById('jumlah-diubah');
            const terapkan = () => {
                const q = cari.value.trim().toLowerCase();
                tabel.classList.toggle('hanya-bersaldo', hanya.checked && q === '');
                tabel.querySelectorAll('tbody tr').forEach((tr) => {
                    tr.style.display = q !== '' && !tr.dataset.cari.includes(q) ? 'none' : '';
                });
            };
            const hitung = () => {
                let n = 0;
                tabel.querySelectorAll('.isian-saldo').forEach((el) => {
                    const ada = el.value.trim() !== '';
                    el.classList.toggle('diubah', ada);
                    if (ada) { n++; el.closest('tr').classList.remove('tanpa-saldo'); }
                });
                info.textContent = n ? n + ' akun diisi' : '';
            };
            cari.addEventListener('input', terapkan);
            hanya.addEventListener('change', terapkan);
            tabel.addEventListener('input', hitung);
            terapkan();
            hitung();
        })();
    </script>
@endsection

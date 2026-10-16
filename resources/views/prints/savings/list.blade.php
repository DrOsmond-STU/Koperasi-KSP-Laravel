@extends('prints.layout')

@section('title', 'Cetakan Simpanan')

@section('print-content')
    @php
        // Total dipisah per kategori produk simpanan (pokok, wajib, sukarela,
        // lainnya). Label kategori dibaca dari data master
        // savings_product_categories (hanya SELECT, tidak mengubah data);
        // kode yang tidak ada di master tetap tampil memakai kodenya sendiri.
        $namaKategori = class_exists(\App\Models\SavingsProductCategory::class)
            ? \App\Models\SavingsProductCategory::query()->pluck('name', 'code')
            : collect();
        $urutanBaku = ['pokok', 'wajib', 'sukarela', 'berjangka'];
        $labelKategori = fn (?string $kode) => $namaKategori[$kode] ?? ($kode ? 'Simpanan '.ucfirst($kode) : 'Tanpa Kategori');
        $urutkanKategori = fn ($totals) => $totals->sortBy(fn ($nilai, $kode) => [
            ($i = array_search($kode, $urutanBaku, true)) === false ? 99 : $i,
            (string) $kode,
        ]);
        $totalPerKategori = fn ($accounts) => $urutkanKategori(
            $accounts->groupBy(fn ($account) => $account->savingsProduct?->category)
                ->map(fn ($group) => (float) $group->sum('balance'))
        );
        $rekap = $totalPerKategori($members->flatMap->savingsAccounts);
    @endphp

    <h2 style="font-size:13pt; margin:0 0 2px;">Cetakan Simpanan Anggota</h2>
    <p style="font-size:9pt; color:#5C6E64; margin:0 0 4px;">{{ $filterDescription }}</p>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    @foreach ($members as $member)
        <table class="data-table" style="margin-bottom: 4px;">
            <thead>
                <tr><th colspan="4">{{ $member->member_number }} — {{ $member->name }}</th></tr>
                <tr>
                    <th>No. Rekening</th>
                    <th>Produk</th>
                    <th>Status</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($member->savingsAccounts as $account)
                    <tr>
                        <td>{{ $account->account_number }}</td>
                        <td>{{ $account->savingsProduct->name }}</td>
                        <td>{{ ucfirst($account->status) }}</td>
                        <td>Rp {{ number_format($account->balance, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada rekening simpanan.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($member->savingsAccounts->isNotEmpty())
            <table style="width:auto; margin:0 0 16px auto; font-size:9pt;">
                @foreach ($totalPerKategori($member->savingsAccounts) as $kode => $nilai)
                    <tr>
                        <td style="padding:1px 12px 1px 0;">Total {{ $labelKategori($kode) }}</td>
                        <td style="padding:1px 0; text-align:right;">Rp {{ number_format($nilai, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:3px 12px 1px 0; border-top:1px solid #16201C; font-weight:700;">Total Seluruh Simpanan</td>
                    <td style="padding:3px 0 1px; border-top:1px solid #16201C; text-align:right; font-weight:700;">Rp {{ number_format($member->savingsAccounts->sum('balance'), 2, ',', '.') }}</td>
                </tr>
            </table>
        @else
            <p style="font-size:9pt; margin:0 0 16px; font-weight:700;">Total Seluruh Simpanan: Rp 0</p>
        @endif
    @endforeach

    @if ($members->count() > 1)
        <table class="data-table" style="margin-top: 8px; page-break-inside: avoid;">
            <thead>
                <tr><th colspan="2">Rekapitulasi Total Simpanan per Jenis — {{ $filterDescription }}</th></tr>
                <tr>
                    <th>Jenis Simpanan</th>
                    <th style="text-align:right;">Total Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rekap as $kode => $nilai)
                    <tr>
                        <td>{{ $labelKategori($kode) }}</td>
                        <td style="text-align:right;">Rp {{ number_format($nilai, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="font-weight:700;">Total Seluruh Simpanan</td>
                    <td style="text-align:right; font-weight:700;">Rp {{ number_format($rekap->sum(), 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection

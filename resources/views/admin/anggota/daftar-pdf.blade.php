@extends('prints.layout')

@section('title', 'Daftar Anggota')

@section('print-content')
    <style>
        .subtitle { font-size: 9pt; color: #5C6E64; margin: 0 0 4px; }
        .generated-at { font-size: 8pt; color: #5C6E64; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #D7E2DB; padding: 5px 7px; text-align: left; }
        th { background: #F4F7F4; font-weight: 700; }
    </style>

    <h2 style="font-size:13pt; margin:0 0 2px;">Daftar Anggota Koperasi</h2>
    <p class="subtitle">{{ $filterDescription }}</p>
    <p class="generated-at">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }} — Total: {{ $members->count() }} anggota</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>No. Anggota</th>
                <th>Nama</th>
                <th>Jenis Anggota</th>
                <th>Cabang</th>
                <th>Status</th>
                <th>Tgl Bergabung</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $index => $member)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $member->member_number }}</td>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->memberType?->name }}</td>
                    <td>{{ $member->branch?->name }}</td>
                    <td>{{ ucfirst($member->status) }}</td>
                    <td>{{ optional($member->joined_at)->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Tidak ada anggota untuk filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

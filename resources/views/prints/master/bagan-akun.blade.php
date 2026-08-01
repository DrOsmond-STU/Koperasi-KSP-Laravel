@extends('prints.layout')

@section('title', 'Bagan Akun')

@section('print-content')
    <h2 style="font-size:13pt; margin:0 0 2px;">Bagan Akun (Chart of Accounts)</h2>
    <p style="font-size:8pt; color:#5C6E64; margin:0 0 14px;">Dicetak: {{ $generatedAt->translatedFormat('d M Y H:i') }}</p>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th><th>Nama Akun</th><th>Tipe</th><th>Grup</th><th>Normal</th><th>Postable</th><th>Induk</th><th>Laporan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->type }}</td>
                    <td>{{ $account->group ?? '—' }}</td>
                    <td>{{ $account->normal_balance }}</td>
                    <td>{{ $account->is_postable ? 'Ya' : 'Header' }}</td>
                    <td>{{ $account->parent_code ?? '—' }}</td>
                    <td>{{ $account->statement === 'NERACA' ? 'Neraca' : 'Laba/Rugi' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Belum ada akun.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

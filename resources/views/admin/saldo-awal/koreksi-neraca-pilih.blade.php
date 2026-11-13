@extends('layouts.app')

@section('title', 'Koreksi Saldo Awal Neraca')

@section('content')
    @include('admin.pinjaman.partials.penyelarasan-gaya')

    <h2>Koreksi Saldo Awal Neraca</h2>
    <p class="tenang" style="margin-top:-8px;">Pilih batch saldo awal yang akan dikoreksi.</p>

    <div class="panel">
        @if ($batches->isEmpty())
            <p class="tenang">Belum ada batch saldo awal.</p>
        @else
            <table class="data-table ringkas">
                <thead><tr><th>Batch</th><th>Cabang</th><th>Cut-off</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($batches as $b)
                        <tr>
                            <td>#{{ $b->id }}</td>
                            <td>{{ $b->branch->name ?? '-' }}</td>
                            <td>{{ $b->cutoff_date->translatedFormat('d M Y') }}</td>
                            <td>{{ $b->isDraft() ? 'Draft' : 'Terkunci' }}</td>
                            <td><a class="tautan-kecil" href="{{ route('admin.saldo-awal.koreksi-neraca', $b) }}">Buka →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

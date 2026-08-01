@extends('prints.layout')

@section('title', $title)

@section('print-content')
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #D7E2DB; padding: 5px 8px; text-align: left; }
        th { background: #F4F7F4; }
    </style>

    <h2 style="font-size:13pt; margin:0 0 2px;">{{ $title }}</h2>
    <p style="font-size:9pt; color:#5C6E64;">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

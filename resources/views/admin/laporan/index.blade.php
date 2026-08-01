@extends('layouts.app')

@section('title', 'Menu Laporan')

@section('content')
    <style>
        .laporan-group { margin-bottom: 22px; }
        .laporan-group h3 { font-size: 14px; margin: 0 0 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .laporan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
        .laporan-card {
            display: block; background: var(--surface); border: 1px solid var(--line); border-radius: 12px;
            padding: 14px 16px; text-decoration: none; color: inherit; transition: transform .1s ease, box-shadow .15s ease;
        }
        .laporan-card:hover, .laporan-card:focus-visible { transform: translateY(-2px); box-shadow: var(--shadow); outline: none; }
        .laporan-card strong { display: block; font-size: 13.5px; margin-bottom: 3px; }
        .laporan-card span { font-size: 11.5px; color: var(--muted); }
    </style>

    <h2 style="margin-bottom: 18px;">Menu Laporan</h2>
    <p style="margin-top: -12px; margin-bottom: 22px; font-size: 13px; color: var(--muted);">
        Semua laporan per form/fitur — bisa dicari, difilter, dan diekspor ke PDF/Excel.
    </p>

    @foreach ($groups as $groupName => $modules)
        <div class="laporan-group">
            <h3>{{ $groupName }}</h3>
            <div class="laporan-grid">
                @foreach ($modules as $module => $definition)
                    <a href="{{ route('admin.laporan.show', $module) }}" class="laporan-card">
                        <strong>{{ $definition['label'] }}</strong>
                        <span>{{ count($definition['columns']) }} kolom</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection

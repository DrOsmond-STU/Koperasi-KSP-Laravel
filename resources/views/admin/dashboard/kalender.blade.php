@extends('layouts.app')

@section('title', 'Dashboard Kalender')

@section('content')
    <style>
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-bar select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day-name { text-align: center; font-size: 11px; font-weight: 700; color: var(--muted); padding: 6px 0; }
        .cal-cell { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; min-height: 90px; padding: 6px; font-size: 11px; }
        .cal-cell.empty { background: transparent; border: none; }
        .cal-date { font-weight: 700; margin-bottom: 4px; }
        .cal-item { border-radius: 6px; padding: 2px 6px; margin-bottom: 3px; font-size: 10px; }
        .cal-item.merah { background: #FBE4E0; color: #A8472F; }
        .cal-item.biru { background: #E3ECF7; color: #2E5AA8; }
        .cal-item.abu { background: #ECECEC; color: #666; text-decoration: line-through; }
        .legend { display: flex; gap: 14px; margin-bottom: 14px; font-size: 12px; }
        .legend span { display: inline-flex; align-items: center; gap: 6px; }
        .legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
    </style>

    <div class="dash-header">
        <h2>Dashboard Kalender {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>
        <a href="{{ route('admin.kalender.kegiatan.create') }}" class="btn-primary">+ Kegiatan Baru</a>
    </div>

    <form class="filter-bar" method="GET" style="margin-bottom: 14px;">
        <select name="branch_id" onchange="this.form.submit()">
            @if (is_null($selectedBranchId) || $branches->count() > 1)
                <option value="" {{ $isConsolidated ? 'selected' : '' }}>{{ $branches->count() > 1 ? 'Semua Cabang (Konsolidasi)' : '' }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" {{ $selectedBranchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <select name="month" onchange="this.form.submit()">
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('F') }}</option>
            @endforeach
        </select>
        <select name="year" onchange="this.form.submit()">
            @foreach (range($year - 2, $year + 2) as $y)
                <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select>
    </form>

    <div class="legend">
        <span><i style="background:#FBE4E0;"></i> Jatuh Tempo Angsuran</span>
        <span><i style="background:#E3ECF7;"></i> Kegiatan Koperasi</span>
        <span><i style="background:#ECECEC;"></i> Kegiatan Dibatalkan</span>
    </div>

    @php
        $firstOfMonth = \Illuminate\Support\Carbon::create($year, $month, 1);
        $daysInMonth = $firstOfMonth->daysInMonth;
        $leadingBlanks = $firstOfMonth->dayOfWeekIso - 1;
    @endphp

    <div class="cal-grid">
        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dayName)
            <div class="cal-day-name">{{ $dayName }}</div>
        @endforeach

        @for ($i = 0; $i < $leadingBlanks; $i++)
            <div class="cal-cell empty"></div>
        @endfor

        @for ($day = 1; $day <= $daysInMonth; $day++)
            @php $dateKey = $firstOfMonth->copy()->day($day)->toDateString(); @endphp
            <div class="cal-cell">
                <div class="cal-date">{{ $day }}</div>
                @foreach ($itemsByDate[$dateKey] ?? [] as $item)
                    <div class="cal-item {{ $item['color'] }}">{{ $item['title'] }}</div>
                @endforeach
            </div>
        @endfor
    </div>
@endsection

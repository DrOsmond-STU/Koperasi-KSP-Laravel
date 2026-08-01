@extends('layouts.app')

@section('title', 'Dashboard Kalender')

@section('content')
    <style>
        .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-bar select { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; }
        .btn-primary { display: inline-block; padding: 9px 16px; background: var(--pine); color: #fff; border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .btn-secondary { display: inline-block; padding: 9px 16px; background: transparent; color: var(--pine-bright); border: 1px solid var(--pine); border-radius: 9px; text-decoration: none; font-weight: 700; font-size: 13px; }
        .header-actions { display: flex; gap: 8px; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-day-name { text-align: center; font-size: 11px; font-weight: 700; color: var(--muted); padding: 6px 0; }
        .cal-cell { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; min-height: 90px; padding: 6px; font-size: 11px; }
        .cal-cell.empty { background: transparent; border: none; }
        .cal-date { font-weight: 700; margin-bottom: 4px; }
        .cal-item { border-radius: 6px; padding: 2px 6px; margin-bottom: 3px; font-size: 10px; cursor: pointer; transition: filter .12s ease; }
        .cal-item:hover, .cal-item:focus-visible { filter: brightness(1.25); outline: none; }
        .cal-item.merah { background: var(--brick-soft); color: var(--brick); }
        .cal-item.biru { background: #1C2B3D; color: #5B8FD9; }
        .cal-item.abu { background: #26282A; color: var(--muted); text-decoration: line-through; }
        .legend { display: flex; gap: 14px; margin-bottom: 14px; font-size: 12px; }
        .legend span { display: inline-flex; align-items: center; gap: 6px; }
        .legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
    </style>

    <div class="dash-header">
        <h2>Dashboard Kalender {{ $isConsolidated ? '— Konsolidasi Seluruh Cabang' : '' }}</h2>
        <div class="header-actions">
            <a href="{{ route('admin.kalender.kegiatan.index') }}" class="btn-secondary">Kelola Daftar Kegiatan</a>
            <a href="{{ route('admin.kalender.kegiatan.create') }}" class="btn-primary">+ Kegiatan Baru</a>
        </div>
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
        <span><i style="background:var(--brick);"></i> Jatuh Tempo Angsuran</span>
        <span><i style="background:#5B8FD9;"></i> Kegiatan Koperasi</span>
        <span><i style="background:var(--muted);"></i> Kegiatan Dibatalkan</span>
    </div>

    @php
        $firstOfMonth = \Illuminate\Support\Carbon::create($year, $month, 1);
        $daysInMonth = $firstOfMonth->daysInMonth;
        $leadingBlanks = $firstOfMonth->dayOfWeekIso - 1;
        $eventStatusLabels = ['terjadwal' => 'Terjadwal', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'];

        $itemDialog = function (array $item, string $dateKey) use ($eventStatusLabels) {
            $tanggal = \Illuminate\Support\Carbon::parse($dateKey)->translatedFormat('d M Y');

            if ($item['type'] === 'jatuh_tempo_angsuran') {
                $rows = [
                    ['label' => 'Anggota', 'value' => $item['member_name']],
                    ['label' => 'No. Pinjaman', 'value' => $item['loan_number']],
                    ['label' => 'Angsuran ke', 'value' => (string) $item['installment_number']],
                    ['label' => 'Jumlah', 'value' => 'Rp '.number_format($item['amount'], 0, ',', '.')],
                    ['label' => 'Jatuh Tempo', 'value' => $tanggal],
                ];
            } else {
                $rows = array_filter([
                    ['label' => 'Tanggal', 'value' => $tanggal],
                    ['label' => 'Waktu', 'value' => \Illuminate\Support\Carbon::parse($item['start_at'])->format('H:i').' - '.\Illuminate\Support\Carbon::parse($item['end_at'])->format('H:i')],
                    $item['location'] ? ['label' => 'Lokasi', 'value' => $item['location']] : null,
                    ['label' => 'Status', 'value' => $eventStatusLabels[$item['status']] ?? ucfirst($item['status'])],
                    $item['description'] ? ['label' => 'Keterangan', 'value' => $item['description']] : null,
                ]);
            }

            return json_encode(['title' => $item['title'], 'rows' => array_values($rows)], JSON_UNESCAPED_UNICODE);
        };
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
                    <div class="cal-item {{ $item['color'] }}" role="button" tabindex="0"
                        data-dialog="{{ $itemDialog($item, $dateKey) }}">{{ $item['title'] }}</div>
                @endforeach
            </div>
        @endfor
    </div>
@endsection

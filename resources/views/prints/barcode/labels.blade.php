<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm; }
        body { font-family: sans-serif; }
        .label-grid { display: table; width: 100%; }
        .label-row { display: table-row; }
        .label { display: table-cell; width: 33.33%; padding: 4mm; text-align: center; border: 1px dashed #D7E2DB; vertical-align: middle; }
        .label img { max-width: 100%; height: 14mm; }
        .label .code { font-size: 8pt; font-weight: 700; margin-top: 2px; }
        .label .name { font-size: 7pt; color: #5C6E64; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="label-grid">
        @foreach ($items->chunk(3) as $row)
            <div class="label-row">
                @foreach ($row as $item)
                    <div class="label">
                        <img src="{{ $item['barcode'] }}" alt="{{ $item['code'] }}">
                        <div class="code">{{ $item['code'] }}</div>
                        <div class="name">{{ $item['name'] }}</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    @if ($items->isEmpty())
        <p>Tidak ada data untuk dicetak.</p>
    @endif
</body>
</html>

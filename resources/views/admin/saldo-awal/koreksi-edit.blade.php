@extends('layouts.app')

@section('title', 'Ubah Baris — '.$label)

@section('content')
    <style>
        .form-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 22px; max-width: 620px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; font-family: inherit; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--pine); text-decoration: none; font-weight: 600; font-size: 13px; }
        .error-text { color: var(--brick); font-size: 12px; margin-bottom: 12px; }
        .notice { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; font-size: 12px; line-height: 1.6; margin-bottom: 16px; }
    </style>

    <h2>Ubah Baris — {{ $label }}</h2>
    <p style="margin-top: -8px;">
        <a href="{{ route('admin.saldo-awal.koreksi.rows', [$batch, $subModule]) }}" class="btn-link">&larr; Kembali ke daftar baris</a>
    </p>

    @if ($errors->any())
        <div class="error-text">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="notice">
        Perubahan di sini mengubah angka yang nanti menjadi <strong>jurnal pembukaan</strong> saat batch dikunci.
        Setiap perubahan tercatat di Keamanan &amp; Audit.
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.saldo-awal.koreksi.update', [$batch, $subModule, $row->id]) }}">
            @csrf
            @method('PUT')

            @foreach ($fields as $name => $field)
                @php
                    $current = old($name, data_get($row, $name));
                    if ($current instanceof \DateTimeInterface) {
                        $current = $current->format('Y-m-d');
                    }
                @endphp

                <div class="field">
                    <label for="f-{{ $name }}">{{ $field['label'] }}</label>

                    @if ($field['type'] === 'select')
                        <select id="f-{{ $name }}" name="{{ $name }}">
                            @foreach ($options[$field['options']] ?? [] as $value => $text)
                                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    @elseif ($field['type'] === 'money')
                        <input id="f-{{ $name }}" type="number" step="0.01" name="{{ $name }}" value="{{ $current }}">
                    @elseif ($field['type'] === 'number')
                        <input id="f-{{ $name }}" type="number" step="1" name="{{ $name }}" value="{{ $current }}">
                    @elseif ($field['type'] === 'date')
                        <input id="f-{{ $name }}" type="date" name="{{ $name }}" value="{{ $current }}">
                    @else
                        <input id="f-{{ $name }}" type="text" name="{{ $name }}" value="{{ $current }}">
                    @endif
                </div>
            @endforeach

            <button type="submit" class="btn-primary">Simpan Perubahan</button>
        </form>
    </div>
@endsection

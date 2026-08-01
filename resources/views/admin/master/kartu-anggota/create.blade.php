@extends('layouts.app')

@section('title', 'Tambah Template Kartu Anggota')

@section('content')
    <style>
        .designer-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; align-items: start; }
        @media (max-width: 1080px) { .designer-grid { grid-template-columns: 1fr; } }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px; }
        .panel h3 { margin-top: 0; font-size: 14px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; font-size: 13px; }
        .field-row { display: flex; gap: 12px; }
        .field-row .field { flex: 1; }
        .btn-primary { padding: 10px 18px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 13px; }
        .btn-secondary { padding: 8px 14px; background: var(--paper); color: var(--pine); border: 1px solid var(--line); border-radius: 9px; font-weight: 700; cursor: pointer; font-size: 12px; }
        .btn-danger-text { color: var(--brick); background: none; border: none; font-weight: 600; cursor: pointer; font-size: 12px; padding: 0; }
        .error-text { color: var(--brick); font-size: 11px; margin-top: 3px; }
        .field-card { border: 1px solid var(--line); border-radius: 10px; padding: 12px; margin-bottom: 12px; background: var(--paper); }
        .field-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .field-card-header strong { font-size: 12px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .mini-field label { font-size: 10px; color: var(--muted); display: block; margin-bottom: 3px; }
        .mini-field input, .mini-field select { width: 100%; box-sizing: border-box; padding: 5px 6px; border: 1px solid var(--line); border-radius: 6px; font-size: 12px; }
        .preview-wrap { background: var(--paper); border-radius: 10px; padding: 20px; display: flex; justify-content: center; overflow: auto; }
        #card-preview { position: relative; background: #FFFFFF; box-shadow: 0 2px 10px rgba(0,0,0,.15); border-radius: 2mm; overflow: hidden; font-family: sans-serif; }
    </style>

    <h2>Tambah Template Kartu Anggota</h2>

    @if ($errors->any())
        <div class="error-text" style="margin-bottom: 12px;">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.master.member-card-templates.store') }}" enctype="multipart/form-data" id="designer-form">
        @csrf

        <div class="designer-grid">
            <div>
                <div class="panel">
                    <h3>Pengaturan Kartu</h3>
                    <div class="field-row">
                        <div class="field">
                            <label>Nama Template</label>
                            <input type="text" name="name" id="tpl-name" value="{{ old('name') }}" required>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label>Lebar (mm)</label>
                            <input type="number" step="0.01" name="width_mm" id="tpl-width" value="{{ old('width_mm', 85.60) }}" required>
                        </div>
                        <div class="field">
                            <label>Tinggi (mm)</label>
                            <input type="number" step="0.01" name="height_mm" id="tpl-height" value="{{ old('height_mm', 53.98) }}" required>
                        </div>
                        <div class="field">
                            <label>Warna Latar</label>
                            <input type="color" name="background_color" id="tpl-bg-color" value="{{ old('background_color', '#FFFFFF') }}">
                        </div>
                    </div>
                    <div class="field">
                        <label>Gambar Latar (opsional)</label>
                        <input type="file" name="background_image" accept="image/png,image/jpeg">
                    </div>
                </div>

                <div class="panel" style="margin-top: 16px;">
                    <div class="field-card-header">
                        <h3 style="margin:0;">Field Kartu</h3>
                        <button type="button" class="btn-secondary" id="add-field-btn">+ Tambah Field</button>
                    </div>
                    <div id="fields-container"></div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 16px;">Simpan Template</button>
            </div>

            <div class="panel">
                <h3>Preview</h3>
                <div class="preview-wrap">
                    <div id="card-preview"></div>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            var FIELD_LABELS = {
                photo: 'Foto', logo: 'Logo Koperasi', name: 'Nama', member_number: 'No. Anggota', branch_name: 'Nama Cabang',
                member_type_name: 'Jenis Anggota', joined_at: 'Tanggal Bergabung', address: 'Alamat', custom_text: 'Teks Bebas',
            };
            var fieldKeys = @json($fieldKeys);
            var sample = @json($sampleMember);
            var container = document.getElementById('fields-container');
            var nextIndex = 0;

            function fieldOptionsHtml(selected) {
                return fieldKeys.map(function (key) {
                    return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + (FIELD_LABELS[key] || key) + '</option>';
                }).join('');
            }

            function addFieldRow(data) {
                data = data || {};
                var index = nextIndex++;
                var card = document.createElement('div');
                card.className = 'field-card';
                card.dataset.index = index;
                card.innerHTML =
                    '<div class="field-card-header"><strong>Field #' + (index + 1) + '</strong><button type="button" class="btn-danger-text" data-action="remove-field">Hapus</button></div>' +
                    '<div class="grid-3" style="margin-bottom:8px;">' +
                        '<div class="mini-field"><label>Jenis Field</label><select name="fields[' + index + '][field_key]" data-role="field_key">' + fieldOptionsHtml(data.field_key) + '</select></div>' +
                        '<div class="mini-field" data-role="custom-text-wrap" style="display:' + (data.field_key === 'custom_text' ? '' : 'none') + ';"><label>Teks</label><input type="text" name="fields[' + index + '][custom_text_value]" data-role="custom_text_value" value="' + (data.custom_text_value || '') + '"></div>' +
                        '<div class="mini-field"><label>Urutan</label><input type="number" name="fields[' + index + '][sort_order]" data-role="sort_order" value="' + (data.sort_order ?? index) + '"></div>' +
                    '</div>' +
                    '<div class="grid-4" style="margin-bottom:8px;">' +
                        '<div class="mini-field"><label>Posisi X (mm)</label><input type="number" step="0.01" name="fields[' + index + '][position_x_mm]" data-role="position_x_mm" value="' + (data.position_x_mm ?? 0) + '"></div>' +
                        '<div class="mini-field"><label>Posisi Y (mm)</label><input type="number" step="0.01" name="fields[' + index + '][position_y_mm]" data-role="position_y_mm" value="' + (data.position_y_mm ?? 0) + '"></div>' +
                        '<div class="mini-field"><label>Lebar (mm)</label><input type="number" step="0.01" name="fields[' + index + '][width_mm]" data-role="width_mm" value="' + (data.width_mm ?? '') + '"></div>' +
                        '<div class="mini-field"><label>Tinggi (mm)</label><input type="number" step="0.01" name="fields[' + index + '][height_mm]" data-role="height_mm" value="' + (data.height_mm ?? '') + '"></div>' +
                    '</div>' +
                    '<div class="grid-4">' +
                        '<div class="mini-field"><label>Ukuran Font (pt)</label><input type="number" name="fields[' + index + '][font_size_pt]" data-role="font_size_pt" value="' + (data.font_size_pt ?? '') + '"></div>' +
                        '<div class="mini-field"><label>Ketebalan</label><select name="fields[' + index + '][font_weight]" data-role="font_weight"><option value="normal"' + (data.font_weight !== 'bold' ? ' selected' : '') + '>Normal</option><option value="bold"' + (data.font_weight === 'bold' ? ' selected' : '') + '>Tebal</option></select></div>' +
                        '<div class="mini-field"><label>Perataan</label><select name="fields[' + index + '][text_align]" data-role="text_align"><option value="left"' + (!data.text_align || data.text_align === 'left' ? ' selected' : '') + '>Kiri</option><option value="center"' + (data.text_align === 'center' ? ' selected' : '') + '>Tengah</option><option value="right"' + (data.text_align === 'right' ? ' selected' : '') + '>Kanan</option></select></div>' +
                        '<div class="mini-field"><label>Warna</label><input type="color" name="fields[' + index + '][color]" data-role="color" value="' + (data.color || '#16201C') + '"></div>' +
                    '</div>' +
                    '<p class="error-text" data-role="bounds-warning" style="display:none;"></p>';

                container.appendChild(card);

                card.querySelectorAll('input, select').forEach(function (el) {
                    el.addEventListener('input', function () {
                        var fieldKeySelect = card.querySelector('[data-role="field_key"]');
                        var customWrap = card.querySelector('[data-role="custom-text-wrap"]');
                        customWrap.style.display = fieldKeySelect.value === 'custom_text' ? '' : 'none';
                        renderPreview();
                    });
                });
                card.querySelector('[data-action="remove-field"]').addEventListener('click', function () {
                    card.remove();
                    renderPreview();
                });

                renderPreview();
            }

            document.getElementById('add-field-btn').addEventListener('click', function () { addFieldRow({}); });

            function readCards() {
                return Array.from(container.querySelectorAll('.field-card')).map(function (card) {
                    return {
                        el: card,
                        field_key: card.querySelector('[data-role="field_key"]').value,
                        custom_text_value: card.querySelector('[data-role="custom_text_value"]')?.value || '',
                        position_x_mm: parseFloat(card.querySelector('[data-role="position_x_mm"]').value) || 0,
                        position_y_mm: parseFloat(card.querySelector('[data-role="position_y_mm"]').value) || 0,
                        width_mm: parseFloat(card.querySelector('[data-role="width_mm"]').value) || 0,
                        height_mm: parseFloat(card.querySelector('[data-role="height_mm"]').value) || 0,
                        font_size_pt: card.querySelector('[data-role="font_size_pt"]').value,
                        font_weight: card.querySelector('[data-role="font_weight"]').value,
                        text_align: card.querySelector('[data-role="text_align"]').value,
                        color: card.querySelector('[data-role="color"]').value,
                    };
                });
            }

            function resolveValue(fieldKey, customText) {
                switch (fieldKey) {
                    case 'name': return sample.name;
                    case 'member_number': return sample.member_number;
                    case 'branch_name': return sample.branch_name;
                    case 'member_type_name': return sample.member_type_name;
                    case 'joined_at': return sample.joined_at;
                    case 'address': return sample.address;
                    case 'custom_text': return customText || '';
                    default: return '';
                }
            }

            function renderPreview() {
                var width = parseFloat(document.getElementById('tpl-width').value) || 0;
                var height = parseFloat(document.getElementById('tpl-height').value) || 0;
                var bgColor = document.getElementById('tpl-bg-color').value;

                var preview = document.getElementById('card-preview');
                preview.style.width = width + 'mm';
                preview.style.height = height + 'mm';
                preview.style.backgroundColor = bgColor;
                preview.innerHTML = '';

                readCards().forEach(function (field) {
                    var style = 'position:absolute;left:' + field.position_x_mm + 'mm;top:' + field.position_y_mm + 'mm;';
                    if (field.width_mm) style += 'width:' + field.width_mm + 'mm;';
                    if (field.height_mm) style += 'height:' + field.height_mm + 'mm;';

                    var el = document.createElement('div');

                    if (field.field_key === 'photo') {
                        el.setAttribute('style', style + 'display:flex;align-items:center;justify-content:center;background:#E9F1EC;color:#11543B;font-weight:700;overflow:hidden;');
                        if (sample.photo_url) {
                            el.innerHTML = '<img src="' + sample.photo_url + '" style="width:100%;height:100%;object-fit:cover;">';
                        } else {
                            el.textContent = (sample.name || '?').substring(0, 1).toUpperCase();
                        }
                    } else if (field.field_key === 'logo') {
                        el.setAttribute('style', style + 'display:flex;align-items:center;justify-content:center;overflow:hidden;');
                        if (sample.logo_url) {
                            el.innerHTML = '<img src="' + sample.logo_url + '" style="max-width:100%;max-height:100%;object-fit:contain;">';
                        } else {
                            el.setAttribute('style', el.getAttribute('style') + 'border:1px dashed #869389;font-size:9px;color:#5C6E64;');
                            el.textContent = 'Logo';
                        }
                    } else {
                        style += 'font-size:' + (field.font_size_pt || 10) + 'pt;font-weight:' + (field.font_weight || 'normal') + ';text-align:' + (field.text_align || 'left') + ';color:' + field.color + ';';
                        el.setAttribute('style', style);
                        el.textContent = resolveValue(field.field_key, field.custom_text_value);
                    }

                    preview.appendChild(el);

                    var right = field.position_x_mm + (field.width_mm || 0);
                    var bottom = field.position_y_mm + (field.height_mm || 0);
                    var warning = field.el.querySelector('[data-role="bounds-warning"]');
                    if (right > width || bottom > height) {
                        warning.textContent = 'Posisi/ukuran field ini melewati batas kartu (' + width + '×' + height + ' mm).';
                        warning.style.display = '';
                    } else {
                        warning.style.display = 'none';
                    }
                });
            }

            document.getElementById('tpl-width').addEventListener('input', renderPreview);
            document.getElementById('tpl-height').addEventListener('input', renderPreview);
            document.getElementById('tpl-bg-color').addEventListener('input', renderPreview);

            // Mulai dengan 1 baris field kosong agar form tidak kosong total.
            addFieldRow({ field_key: 'name', position_x_mm: 4, position_y_mm: 4, font_size_pt: 11, font_weight: 'bold' });
        })();
    </script>
@endsection

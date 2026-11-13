{{-- Gaya bersama layar Penyelarasan Jadwal Angsuran (daftar, rincian, riwayat). --}}
<style>
    .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 20px; }
    .status-msg { color: var(--ok); font-size: 13px; margin-bottom: 14px; }
    .error-msg { color: var(--brick); font-size: 13px; margin-bottom: 14px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); font-size: 12.5px; vertical-align: top; }
    .data-table th { background: var(--paper); font-weight: 700; color: var(--muted); }
    .data-table td.num, .data-table th.num { text-align: right; white-space: nowrap; }
    .data-table.ringkas { max-width: 760px; }
    .selisih-plus { color: var(--brick); font-weight: 700; }
    .selisih-minus { color: var(--pine-ink); font-weight: 700; }
    .warn-note { color: var(--brick); font-size: 11px; margin: 4px 0 0; }
    .info-note { color: var(--muted); font-size: 11px; margin: 4px 0 0; }
    .ok-note { color: var(--ok); font-size: 14px; font-weight: 600; }
    .btn-save { padding: 9px 16px; background: var(--pine); color: #fff; border: none; border-radius: 9px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-danger { padding: 6px 12px; background: transparent; color: var(--brick); border: 1px solid var(--brick); border-radius: 7px; font-weight: 700; cursor: pointer; font-size: 12px; }
    .select-all-row { margin-bottom: 10px; font-size: 13px; }
    .konfirmasi { display: flex; gap: 8px; align-items: flex-start; margin: 14px 0; font-size: 13px; max-width: 720px; }
    .tenang { color: var(--muted); font-size: 12.5px; }
    .badge { display: inline-block; padding: 1px 7px; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-lunas { background: #e6f4ea; color: #1e6b3a; }
    .badge-aktif { background: var(--paper); color: var(--muted); }
    .tautan-kembali, .tautan-kecil { color: var(--pine); font-size: 12.5px; font-weight: 600; text-decoration: none; white-space: nowrap; }
    .tautan-kembali:hover, .tautan-kecil:hover { text-decoration: underline; }
    .rincian-baris tr.baris-berubah td { background: rgba(255, 191, 0, 0.08); }
    .rincian-baris.hanya-berubah tr.baris-tetap { display: none; }
    .rincian-baris s { text-decoration: line-through; }
</style>

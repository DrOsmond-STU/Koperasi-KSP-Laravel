{{--
    Pagination tabel daftar (permintaan Okt 2026: "semua list datatables jangan
    di-show semua, harus pakai pagination"). Murni tampilan di sisi klien —
    server tetap merender baris yang sama, jadi pencarian, filter, baris TOTAL
    dan SUB TOTAL, serta Export PDF/Excel tetap bekerja atas SELURUH data yang
    tersaring, bukan hanya halaman yang sedang tampil.

    Berlaku otomatis untuk:
    - datatable generik <div class="dt-wrap" data-dt> (menu Laporan, Jurnal
      Umum) — dipaginasi SETELAH filter/pencarian diterapkan;
    - setiap <table class="data-table"> berisi lebih dari satu halaman baris.

    Dikecualikan: tabel ber-atribut data-no-paginate (laporan keuangan,
    dashboard, rincian saldo awal) dan tabel yang menjadi bagian sebuah <form>
    isian (baris input yang tersembunyi mudah terlewat saat mengisi).

    Baris di luar halaman diberi kelas dt-row-paged — SENGAJA bukan
    dt-row-hidden, karena dt-row-hidden berarti "tersaring" dan dipakai
    penghitung TOTAL/SUB TOTAL.
--}}
<style>
    tr.dt-row-paged { display: none !important; }
    .dt-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin: 10px 0 4px; font-size: 12.5px; color: var(--muted); }
    .dt-pager-info { white-space: nowrap; }
    .dt-pager-note { flex-basis: 100%; font-size: 11.5px; }
    .dt-pager-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .dt-pager-nav button { min-width: 32px; padding: 5px 9px; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); color: inherit; font: inherit; cursor: pointer; }
    .dt-pager-nav button[aria-current="page"] { background: var(--pine); border-color: var(--pine); color: #fff; font-weight: 700; }
    .dt-pager-nav button:disabled { opacity: .45; cursor: default; }
    .dt-pager-nav .dt-pager-gap { padding: 0 4px; }
    .dt-pager-size { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
    .dt-pager-size select { padding: 5px 8px; border: 1px solid var(--line); border-radius: 7px; background: var(--surface); color: inherit; font: inherit; }
</style>
<script>
    (function () {
        var PILIHAN_UKURAN = [10, 25, 50, 100];
        var UKURAN_BAWAAN = 25;

        function angka(n) { return n.toLocaleString('id-ID'); }

        // semuaBaris: baris <tbody> yang dikelola. Baris ber-kelas
        // dt-row-hidden (tersaring) tidak ikut dihitung ke halaman mana pun.
        function pasangPager(setelah, semuaBaris, opsi) {
            var ukuran = UKURAN_BAWAAN;
            var halaman = 1;

            var pager = document.createElement('div');
            pager.className = 'dt-pager';
            pager.innerHTML =
                '<span class="dt-pager-info"></span>' +
                '<div class="dt-pager-nav" role="navigation" aria-label="Halaman tabel"></div>' +
                '<label class="dt-pager-size">Tampilkan <select aria-label="Baris per halaman"></select> per halaman</label>' +
                (opsi.catatan ? '<span class="dt-pager-note">' + opsi.catatan + '</span>' : '');
            setelah.parentNode.insertBefore(pager, setelah.nextSibling);

            var infoEl = pager.querySelector('.dt-pager-info');
            var navEl = pager.querySelector('.dt-pager-nav');
            var sizeEl = pager.querySelector('select');
            PILIHAN_UKURAN.forEach(function (n) {
                var o = document.createElement('option');
                o.value = n;
                o.textContent = n;
                if (n === ukuran) o.selected = true;
                sizeEl.appendChild(o);
            });

            function tombol(label, ke, aktif, judul) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = label;
                if (judul) b.title = judul;
                if (aktif) b.setAttribute('aria-current', 'page');
                if (ke === null) b.disabled = true; else b.dataset.ke = ke;
                navEl.appendChild(b);
            }

            function render() {
                var kandidat = semuaBaris.filter(function (r) { return !r.classList.contains('dt-row-hidden'); });
                var jumlah = kandidat.length;
                var maks = Math.max(1, Math.ceil(jumlah / ukuran));
                if (halaman > maks) halaman = maks;
                var awal = (halaman - 1) * ukuran;
                var akhir = Math.min(awal + ukuran, jumlah);

                semuaBaris.forEach(function (r) { r.classList.remove('dt-row-paged'); });
                kandidat.forEach(function (r, i) { if (i < awal || i >= akhir) r.classList.add('dt-row-paged'); });

                pager.hidden = jumlah <= PILIHAN_UKURAN[0];
                infoEl.textContent = jumlah === 0 ? 'Tidak ada baris'
                    : 'Baris ' + angka(awal + 1) + '–' + angka(akhir) + ' dari ' + angka(jumlah) + ' · Halaman ' + halaman + ' dari ' + maks;

                navEl.innerHTML = '';
                tombol('«', halaman > 1 ? 1 : null, false, 'Halaman pertama');
                tombol('‹', halaman > 1 ? halaman - 1 : null, false, 'Sebelumnya');
                var dari = Math.max(1, halaman - 2);
                var sampai = Math.min(maks, halaman + 2);
                if (dari > 1) { tombol('1', 1, false); if (dari > 2) navEl.insertAdjacentHTML('beforeend', '<span class="dt-pager-gap">…</span>'); }
                for (var p = dari; p <= sampai; p++) tombol(String(p), p, p === halaman);
                if (sampai < maks) { if (sampai < maks - 1) navEl.insertAdjacentHTML('beforeend', '<span class="dt-pager-gap">…</span>'); tombol(String(maks), maks, false); }
                tombol('›', halaman < maks ? halaman + 1 : null, false, 'Berikutnya');
                tombol('»', halaman < maks ? maks : null, false, 'Halaman terakhir');
            }

            navEl.addEventListener('click', function (e) {
                var b = e.target.closest('button[data-ke]');
                if (!b) return;
                halaman = parseInt(b.dataset.ke, 10) || 1;
                render();
            });
            sizeEl.addEventListener('change', function () {
                ukuran = parseInt(sizeEl.value, 10) || UKURAN_BAWAAN;
                halaman = 1;
                render();
            });

            return {
                render: render,
                keHalamanPertama: function () { halaman = 1; },
                elemen: pager,
            };
        }

        function jalankanSetelahHalamanSiap(fn) {
            // setTimeout(0): jalan sesudah semua handler sinkron lain — datatable
            // dan penghitung SUB TOTAL — selesai menandai baris tersaring.
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { setTimeout(fn, 0); });
            } else {
                setTimeout(fn, 0);
            }
        }

        // 1) Datatable generik (.dt-wrap[data-dt]).
        document.querySelectorAll('.dt-wrap[data-dt]').forEach(function (wrap) {
            var table = wrap.querySelector('.dt-table');
            if (!table || table.hasAttribute('data-no-paginate')) return;
            var baris = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            if (baris.length <= UKURAN_BAWAAN) return;

            var pager = pasangPager(wrap.querySelector('.dt-table-scroll') || table, baris, {
                catatan: wrap.dataset.sumColumns
                    ? 'Baris TOTAL dan SUB TOTAL menjumlah seluruh data yang tersaring (semua halaman), bukan hanya halaman ini.'
                    : '',
            });

            var tertunda = null;
            function jadwalkan() {
                clearTimeout(tertunda);
                tertunda = setTimeout(pager.render, 0);
            }
            ['input', 'change'].forEach(function (jenis) {
                wrap.addEventListener(jenis, function (e) {
                    if (pager.elemen.contains(e.target)) return;
                    pager.keHalamanPertama();
                    jadwalkan();
                });
            });
            jalankanSetelahHalamanSiap(pager.render);
        });

        // 2) Tabel daftar biasa (table.data-table).
        document.querySelectorAll('table.data-table').forEach(function (table) {
            if (table.hasAttribute('data-no-paginate') || table.closest('.dt-wrap') || table.closest('form')) return;
            var baris = [];
            Array.prototype.forEach.call(table.tBodies, function (tb) {
                baris = baris.concat(Array.prototype.slice.call(tb.rows));
            });
            if (baris.length <= UKURAN_BAWAAN) return;

            // Kalau tabel dibungkus <div> khusus (mis. untuk scroll horizontal),
            // pager ditaruh di bawah pembungkusnya.
            var induk = table.parentElement;
            var setelah = induk && induk.tagName === 'DIV' && induk.children.length === 1 ? induk : table;
            var pager = pasangPager(setelah, baris, {});
            jalankanSetelahHalamanSiap(pager.render);
        });
    })();
</script>

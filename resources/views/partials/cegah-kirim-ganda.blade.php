{{--
    Cegah kirim ganda (permintaan Okt 2026, setelah audit menemukan jurnal
    #3780 & #13163 terlanjur dibalik dua kali karena tombol terklik dua kali).
    Setiap form POST hanya bisa dikirim SEKALI: tombolnya dikunci dan diberi
    tanda "Memproses…" sampai halaman berikutnya terbuka. Pengaman sebenarnya
    tetap di server (JournalEngine::reverse menolak pembalikan kedua) — ini
    hanya supaya pengguna tidak perlu melihat pesan penolakan itu.

    Form dengan konfirmasi (onsubmit="return confirm(...)") yang dibatalkan
    pengguna tidak ikut terkunci. Form ber-atribut data-boleh-kirim-ulang
    dikecualikan. Kunci dilepas lagi setelah 15 detik (mis. form yang
    responsnya unduhan berkas, sehingga halaman tidak berpindah) dan saat
    pengguna kembali ke halaman lewat tombol Back.
--}}
<style>
    button.sedang-dikirim, input.sedang-dikirim { opacity: .6; cursor: progress !important; }
</style>
<script>
    (function () {
        function tombolKirim(form) {
            return Array.prototype.filter.call(form.elements, function (el) {
                return (el.tagName === 'BUTTON' && (el.type || 'submit') === 'submit')
                    || (el.tagName === 'INPUT' && (el.type === 'submit' || el.type === 'image'));
            });
        }

        function lepas(form) {
            delete form.dataset.sedangDikirim;
            tombolKirim(form).forEach(function (b) {
                b.disabled = false;
                b.classList.remove('sedang-dikirim');
                if (b.dataset.labelAsli !== undefined) {
                    if (b.tagName === 'BUTTON') { b.textContent = b.dataset.labelAsli; } else { b.value = b.dataset.labelAsli; }
                    delete b.dataset.labelAsli;
                }
            });
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
            if (form.hasAttribute('data-boleh-kirim-ulang')) return;
            // Dibatalkan oleh confirm()/validasi lain — biarkan bisa dicoba lagi.
            if (event.defaultPrevented) return;

            if (form.dataset.sedangDikirim) {
                event.preventDefault();
                return;
            }
            form.dataset.sedangDikirim = '1';

            // Dikunci SETELAH browser mengambil isi form, supaya nama/nilai
            // tombol yang diklik tetap ikut terkirim.
            setTimeout(function () {
                tombolKirim(form).forEach(function (b) {
                    b.disabled = true;
                    b.classList.add('sedang-dikirim');
                    if (b === event.submitter || tombolKirim(form).length === 1) {
                        if (b.tagName === 'BUTTON') { b.dataset.labelAsli = b.textContent; b.textContent = 'Memproses…'; }
                        else if (b.type === 'submit') { b.dataset.labelAsli = b.value; b.value = 'Memproses…'; }
                    }
                });
            }, 0);
            setTimeout(function () { lepas(form); }, 15000);
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                document.querySelectorAll('form[data-sedang-dikirim]').forEach(lepas);
            }
        });
    })();
</script>

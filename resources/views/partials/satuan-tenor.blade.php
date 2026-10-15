{{-- Label satuan di sebelah kolom tenor mengikuti produk yang sedang dipilih,
     supaya angka yang diketik tidak salah dibaca sebagai bulan padahal
     produknya ditagih harian. Dipakai bersama oleh form pengajuan staf,
     portal anggota, dan POS hutang — ketiganya memilih produk lebih dulu.

     Butuh dua penanda di halaman pemakainya:
       - select produknya diberi atribut  data-produk-pinjaman
       - tiap <option> diberi              data-satuan="{{ $p->satuanTenor() }}"
       - kata satuannya dibungkus          <span data-label-satuan>bulan</span> --}}
<script>
    (function () {
        var produk = document.querySelector("[data-produk-pinjaman]");
        if (!produk) { return; }

        function terapkan() {
            var pilihan = produk.options[produk.selectedIndex];
            var satuan = (pilihan && pilihan.getAttribute("data-satuan")) || "bulan";
            document.querySelectorAll("[data-label-satuan]").forEach(function (el) {
                el.textContent = satuan;
            });
        }

        produk.addEventListener("change", terapkan);
        terapkan();
    })();
</script>

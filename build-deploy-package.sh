#!/usr/bin/env bash
#
# Membuat paket ZIP siap-upload ke shared hosting cPanel (DomaiNesia).
#
# Dijalankan di komputer lokal / CI — BUKAN di server. Hasilnya
# `koperasi-app-deploy.zip` berisi proyek Laravel lengkap termasuk
# `vendor/`, sehingga server tidak perlu punya Composer sama sekali
# (jalur §3B di PANDUAN_INSTALASI_SHARED_HOSTING.md).
#
# Pakai:
#   ./build-deploy-package.sh
#
set -euo pipefail

cd "$(dirname "$0")"

OUT="koperasi-app-deploy.zip"

export COMPOSER_ALLOW_SUPERUSER=1

# `composer install` (bukan `update`) hanya memasang versi yang sudah
# terkunci di composer.lock, dan isi vendor/ adalah PHP murni tanpa
# tahap kompilasi — jadi paket ini tetap valid di server PHP 8.3 walau
# mesin build memakai versi PHP yang lebih baru.
echo "==> Memasang dependensi produksi (tanpa dev)"
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

# Catatan: `npm run build` sengaja TIDAK dijalankan. Seluruh halaman
# admin/staf/portal memakai CSS inline; satu-satunya blade yang memanggil
# @vite (resources/views/welcome.blade.php) tidak terdaftar di routes —
# route "/" langsung redirect ke /login. Lihat §1 panduan.

echo "==> Membersihkan cache build yang tidak boleh ikut"
rm -f bootstrap/cache/*.php

# Kalau dist archive tidak terjangkau (mis. jaringan build diblokir proxy),
# Composer diam-diam jatuh ke instalasi via `git clone` dan setiap paket
# membawa `.git`-nya sendiri — pada proyek ini itu 486 MB dari 666 MB isi
# vendor/. Riwayat git dependensi tidak dipakai sama sekali saat runtime,
# jadi selalu dibuang di sini (aman untuk kedua jalur instalasi).
echo "==> Membuang .git bawaan dependensi di vendor/"
find vendor -name '.git' -type d -prune -exec rm -rf {} + 2>/dev/null || true

# Suite tes, dokumentasi, dan sample bawaan dependensi tidak pernah dieksekusi
# di produksi tapi memakan ~90 MB dari 191 MB isi vendor/ — memperlambat upload
# ke shared hosting yang koneksinya terbatas. Set KEEP_VENDOR_TESTS=1 untuk
# melewati langkah ini.
if [ "${KEEP_VENDOR_TESTS:-0}" != "1" ]; then
    echo "==> Membuang tes & dokumentasi dependensi"
    find vendor -maxdepth 3 -type d \
        \( -name tests -o -name Tests -o -name test \
        -o -name docs -o -name doc \
        -o -name samples -o -name examples -o -name benchmarks \) \
        -prune -exec rm -rf {} + 2>/dev/null || true

    # Autoloader hasil --optimize-autoloader memuat classmap statis, jadi
    # penghapusan di atas harus dipastikan tidak memutus kelas yang dipakai.
    echo "==> Memverifikasi autoloader setelah pemangkasan"
    php -r 'require "vendor/autoload.php"; require "bootstrap/app.php";' \
        || { echo "GAGAL: autoloader rusak setelah pemangkasan."; exit 1; }
fi

if [ -f "$OUT" ]; then
    mv -f "$OUT" "$OUT.bak-$(date +%Y%m%d%H%M%S)"
    echo "    ZIP lama dipindah ke backup"
fi

echo "==> Mengemas $OUT"
# -x mengecualikan: repositori git, artefak dev, kredensial, dan file
# yang harus dibuat ulang di server.
zip -r -q "$OUT" . \
    -x '.git/*' \
    -x '*/.git/*' \
    -x '.github/*' \
    -x '.claude/*' \
    -x 'node_modules/*' \
    -x 'tests/*' \
    -x 'database-dumps/*' \
    -x '.env' \
    -x '.env.backup' \
    -x '.env.production' \
    -x '*.zip' \
    -x '*.zip.bak-*' \
    -x 'storage/logs/*.log' \
    -x 'storage/framework/cache/data/*' \
    -x 'storage/framework/sessions/*' \
    -x 'storage/framework/views/*.php' \
    -x 'storage/installed' \
    -x 'public/storage' \
    -x 'public/hot' \
    -x '.phpunit.result.cache' \
    -x 'phpunit.xml' \
    -x '*.DS_Store'

# Pengecualian isi storage/ di atas ikut membuang `.gitignore` penjaga
# folder, dan zip tidak menyimpan folder yang jadi kosong sama sekali.
# Akibatnya `storage/framework/sessions` hilang dari paket dan Laravel
# gagal boot begitu ada yang memindah SESSION_DRIVER kembali ke `file`.
# Kerangka foldernya dikembalikan di sini.
echo "==> Mengembalikan kerangka folder storage/"
zip -q "$OUT" \
    storage/framework/sessions/.gitignore \
    storage/framework/cache/data/.gitignore \
    storage/logs/.gitignore 2>/dev/null || true

echo
echo "Selesai: $OUT ($(du -h "$OUT" | cut -f1))"
echo
echo "Langkah berikutnya — lihat PANDUAN_DEPLOY_DOMAINESIA.md:"
echo "  1. Upload zip lewat cPanel File Manager ke /home/<user>/koperasi-app/"
echo "  2. Extract di sana (JANGAN di dalam public_html)"
echo "  3. Arahkan Document Root domain ke /home/<user>/koperasi-app/public"
echo "  4. Buka https://sik-kppd.com/install"

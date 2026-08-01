# Panduan Deploy — DomaiNesia · `sik-kppd.com`

Panduan ringkas khusus **DomaiNesia (shared hosting/cPanel)** dengan domain
**`sik-kppd.com`**.

Dokumen ini hanya memuat langkah yang spesifik ke DomaiNesia + domain di
atas. Penjelasan panjang tiap langkah (kenapa, alternatif, troubleshooting)
tetap ada di **`PANDUAN_INSTALASI_SHARED_HOSTING.md`** — dirujuk dengan
tanda §.

> **Semua langkah di bawah dikerjakan manual lewat browser (cPanel +
> panel DomaiNesia).** Tidak ada satu pun yang bisa dijalankan dari sisi
> repo ini — lihat catatan di bagian akhir.

---

## 0. Yang harus sudah ada sebelum mulai

| Item | Keterangan |
|---|---|
| Paket hosting DomaiNesia aktif | Paket apa pun yang menyediakan cPanel + MySQL. Pilih lokasi server **Indonesia** agar latensi rendah untuk pengguna koperasi. |
| Domain `sik-kppd.com` | Sudah dibeli — di DomaiNesia atau registrar lain (§1 di bawah membahas keduanya). |
| File `koperasi-app-deploy.zip` | Hasil `./build-deploy-package.sh` (≈49 MB). |

Nama domain tidak membedakan huruf besar/kecil — `SIK-KPPD.com` dan
`sik-kppd.com` adalah domain yang sama. Di seluruh konfigurasi di bawah
ditulis huruf kecil, karena itu bentuk yang dipakai DNS dan sertifikat SSL.

---

## 1. Arahkan domain ke hosting

**Kalau domain dibeli di DomaiNesia:** biasanya sudah otomatis terhubung.
Cek di **Member Area DomaiNesia → Domain → Kelola → Nameserver**, pastikan
memakai nameserver hosting DomaiNesia (`ns1.domainesia.com` /
`ns2.domainesia.com`, atau nameserver yang tertera di email aktivasi
hosting Anda — **pakai yang tertera di email**, jangan menyalin dari sini
begitu saja).

**Kalau domain dibeli di registrar lain**, ubah nameserver di registrar itu
ke nameserver DomaiNesia di atas.

Propagasi DNS butuh **1–24 jam**. Selama belum propagasi, `sik-kppd.com`
belum bisa dibuka — langkah §2–§6 tetap bisa dikerjakan lebih dulu, hanya
§7 (SSL) dan §9 (verifikasi) yang harus menunggu.

Cek propagasi: `nslookup sik-kppd.com` — hasilnya harus IP server hosting
Anda (lihat cPanel → **General Information → Shared IP Address**).

---

## 2. Siapkan PHP (cPanel → Select PHP Version)

1. Set versi ke **PHP 8.3** (atau lebih baru — `composer.json` mensyaratkan
   `^8.3`).
2. Di tab **Extensions**, pastikan aktif:
   `openssl, pdo_mysql, mbstring, tokenizer, xml, ctype, json, bcmath,
   fileinfo, gd, zip, curl`
3. Di tab **Options**, naikkan bila masih kecil:
   - `upload_max_filesize` → **64M** (upload zip lewat File Manager)
   - `post_max_size` → **64M**
   - `max_execution_time` → **300** (migrasi + seed data master)
   - `memory_limit` → **256M**

Detail: §1 panduan utama.

---

## 3. Buat database (cPanel → MySQL Databases)

1. Buat database, misalnya `koperasi`.
2. Buat user MySQL + password kuat.
3. **Add User to Database** → beri **ALL PRIVILEGES**.

cPanel otomatis menambahkan prefix username akun Anda, jadi nama aslinya
akan menjadi seperti `sikkppd_koperasi` dan `sikkppd_admin`. **Catat nama
lengkap berikut prefiksnya** — itu yang dipakai di `.env`, bukan nama
pendek yang Anda ketik.

Detail: §2 panduan utama.

---

## 4. Upload & ekstrak aplikasi

1. Jalankan di komputer lokal:
   ```bash
   ./build-deploy-package.sh
   ```
   Menghasilkan `koperasi-app-deploy.zip` — sudah berisi `vendor/`
   lengkap, jadi **server tidak perlu punya Composer**.

2. cPanel → **File Manager** → masuk ke `/home/<user-cpanel>/`
   (satu level **di atas** `public_html`).
3. Buat folder `koperasi-app`, masuk ke dalamnya.
4. **Upload** `koperasi-app-deploy.zip` ke folder itu.
5. Klik kanan file zip → **Extract**.

> **Jangan ekstrak di dalam `public_html`.** Kalau seluruh proyek berada di
> dalam document root, file `.env` (berisi password database) bisa terunduh
> lewat browser. Struktur yang benar: proyek di
> `/home/<user>/koperasi-app/`, dan hanya isi `public/` yang terekspos.

Detail: §3B panduan utama.

---

## 5. Arahkan document root domain ke `public/`

cPanel → **Domains** → pilih `sik-kppd.com` → **Manage** → ubah
*Document Root* menjadi:

```
/home/<user-cpanel>/koperasi-app/public
```

Kalau paket hosting Anda tidak mengizinkan mengubah document root, pakai
**Cara B** di §4 panduan utama (menyalin isi `public/` ke `public_html`
lalu menyesuaikan dua baris `require` di `index.php`).

---

## 6. Aktifkan SSL (cPanel → SSL/TLS Status)

Pilih `sik-kppd.com` dan `www.sik-kppd.com` → **Run AutoSSL**.

AutoSSL baru bisa terbit setelah DNS di §1 selesai propagasi. Kerjakan
sebelum §7 supaya installer bisa dibuka lewat `https://` sejak awal dan
Anda tidak perlu mengulang login pertama di atas koneksi tanpa enkripsi.

Detail: §10 panduan utama.

---

## 7. Jalankan installer web

Buka:

```
https://sik-kppd.com/install
```

Wizard 5 langkah akan mengerjakan sendiri: cek persyaratan, tulis `.env`
(termasuk `APP_KEY`), tes koneksi database, migrasi + seed data master,
symlink storage, pembuatan Cabang pertama + akun Admin Sistem, lalu
`config:cache`/`route:cache`/`view:cache`.

Isian pada **Langkah 2**:

| Kolom | Isi |
|---|---|
| Nama Aplikasi | `Koperasi Sejahtera Bersama` (atau nama koperasi Anda) |
| URL Aplikasi | `https://sik-kppd.com` |
| Database Host | `127.0.0.1` |
| Database Name / User / Password | dari §3 — **lengkap dengan prefix** |
| Redis | **kosongkan** — DomaiNesia shared hosting tidak menyediakan Redis; aplikasi otomatis memakai driver `database` |

Selesai langkah 5, `/install` otomatis terkunci dan tidak bisa diakses
ulang. Detail: §0 panduan utama.

> **Alternatif tanpa wizard:** salin `.env.domainesia.example` menjadi
> `.env`, isi kredensial database, lalu jalankan `php artisan key:generate`
> dan `php artisan migrate --seed` lewat SSH. Template itu sudah berisi
> profil shared hosting yang benar (semua driver ke `database`/`local`).
> Jangan pakai `.env.production.example` — file itu untuk deployment
> container ber-Redis/S3 dan tidak akan jalan di sini.

---

## 7b. Wajib: kunci cookie sesi setelah installer selesai

Wizard `/install` membuat `.env` dari `.env.example`, dan hanya menimpa
kunci yang memang diisinya (`APP_*`, `DB_*`, `SESSION_DRIVER`,
`CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_*`). Dua kunci keamanan sesi
**tidak** ikut ditulis, sehingga terbawa nilai development dari
`.env.example`: `SESSION_ENCRYPT=false`, dan `SESSION_SECURE_COOKIE` tidak
ada sama sekali (artinya *off*).

Akibatnya cookie sesi tetap dikirim lewat HTTP biasa dan tidak terenkripsi
— padahal aplikasi ini memegang data keuangan anggota. Perbaiki sekali
setelah §7 selesai.

cPanel → **File Manager** → `/home/<user-cpanel>/koperasi-app/.env` →
**Edit**, lalu pastikan ketiga baris ini ada (tambahkan kalau belum):

```
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Simpan, lalu muat ulang konfigurasi yang sudah di-cache — kalau tidak,
perubahan `.env` tidak akan terbaca sama sekali. Lewat SSH:

```bash
cd /home/<user-cpanel>/koperasi-app && php artisan config:cache
```

Tanpa akses SSH: hapus file `bootstrap/cache/config.php` lewat File
Manager. Laravel akan membangunnya ulang otomatis pada request berikutnya.

> Lakukan ini **setelah** SSL di §6 aktif. Kalau `SESSION_SECURE_COOKIE=true`
> disetel sementara domain masih HTTP, login akan gagal diam-diam tanpa
> pesan error (cookie tidak pernah terkirim balik oleh browser).

---

## 8. Pasang 2 Cron Job (cPanel → Cron Jobs)

Keduanya jadwal **Every Minute** (`* * * * *`). Ganti `<user-cpanel>`
dengan username cPanel Anda:

```
* * * * * cd /home/<user-cpanel>/koperasi-app && php artisan schedule:run >> /dev/null 2>&1
```

```
* * * * * cd /home/<user-cpanel>/koperasi-app && php artisan queue:work --stop-when-empty --tries=3 --max-time=50 >> /dev/null 2>&1
```

Baris pertama menjalankan tugas terjadwal (penyusutan aktiva bulanan,
pengingat jatuh tempo angsuran). Baris kedua memproses antrian (export
laporan, notifikasi) lalu berhenti sendiri — pengganti `queue:work` permanen
yang tidak diizinkan di shared hosting.

**Laravel Horizon tidak dipakai di sini** (butuh ekstensi `pcntl` yang tidak
tersedia di shared hosting) — jangan pasang cron untuk Horizon.

Detail: §9 panduan utama.

---

## 9. Verifikasi

- [ ] `https://sik-kppd.com` membuka halaman login (bukan error 500 / listing folder)
- [ ] Gembok SSL hijau di address bar
- [ ] `https://sik-kppd.com/install` sekarang redirect ke `/login`
- [ ] `SESSION_ENCRYPT=true` dan `SESSION_SECURE_COOKIE=true` sudah ada di `.env` (§7b)
- [ ] Login dengan akun Admin Sistem dari wizard langkah 4
- [ ] **Aktifkan 2FA sungguhan (scan QR) dan ganti password** — akun dari
      wizard hanya melewati syarat 2FA untuk sekali login pertama
- [ ] Upload foto anggota berhasil tampil (memastikan storage berfungsi)
- [ ] `.env` **tidak** bisa diakses dari `https://sik-kppd.com/.env`
      (harus 404/403 — kalau isinya malah terunduh, §4 salah: proyek
      terekstrak di dalam `public_html`)

Detail & troubleshooting: §12–§13 panduan utama.

---

## Catatan: kenapa langkah ini manual

Deployment ini tidak dapat dijalankan otomatis dari repo. Connector
**MCP DomaiNesia** yang terpasang di sesi ini berstatus *connected* tetapi
**tidak mengekspos satu pun tool** yang bisa dipanggil — tidak ada tool
untuk membuat akun hosting, mengatur DNS, mengunggah file, maupun
menjalankan perintah di server. Karena itu penyediaan hosting, pengarahan
domain, dan upload harus dikerjakan lewat cPanel/Member Area seperti di
atas.

Yang sudah disiapkan dari sisi repo:

- `build-deploy-package.sh` — pembuat paket ZIP siap-upload
- `.env.domainesia.example` — template `.env` khusus profil shared hosting,
  sudah terisi `sik-kppd.com`
- Panduan ini

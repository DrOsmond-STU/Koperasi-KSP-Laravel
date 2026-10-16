# Panduan Instalasi — Shared Hosting (cPanel)

Aplikasi: **Koperasi Sejahtera Bersama** (Laravel 13, PHP, MySQL)

Panduan ini ditulis untuk hosting **shared/cPanel** biasa (bukan VPS) — asumsi:
tidak ada akses root, tidak ada Supervisor/systemd, dan proses background
tidak boleh berjalan selamanya. Semua langkah di bawah sudah disesuaikan
dengan batasan itu.

---

## 0. Cara Tercepat — Installer Otomatis (disarankan)

Setelah kode ter-upload dan document root diarahkan ke `public/` (§3-§4 di
bawah masih manual, keduanya wajib dilakukan lebih dulu lewat cPanel), sisa
proses instalasi — yang di versi lama panduan ini ada di §5 s.d. §9 dan §11 —
sekarang otomatis lewat wizard berbasis browser, **tidak perlu SSH/tinker
sama sekali**:

1. Buka `https://domainanda.com/install` di browser.
2. **Langkah 1 — Persyaratan**: aplikasi mengecek sendiri versi PHP, ekstensi
   yang aktif, dan permission folder `storage/` dan `bootstrap/cache`. Bereskan
   dulu baris yang berstatus "Gagal" (biasanya lewat menu **MultiPHP INI
   Editor** di cPanel), lalu muat ulang halaman.
3. **Langkah 2 — Aplikasi & Database**: isi nama aplikasi, URL, dan kredensial
   MySQL (database-nya sendiri tetap harus dibuat lebih dulu lewat menu
   **MySQL Databases**, lihat §2 — aplikasi hanya menyambung, tidak bisa
   membuat database baru). Koneksi dites langsung sebelum disimpan. Kolom
   Redis boleh dikosongkan — aplikasi otomatis mendeteksi apakah Redis
   terjangkau; kalau tidak, otomatis beralih ke session/cache/queue berbasis
   database tanpa perlu diisi manual (persis fallback yang dulu dijelaskan di
   §5 versi manual).
4. **Langkah 3 — Migrasi & Storage**: satu klik menjalankan migrasi tabel,
   seed data master (bagan akun, role & permission, dst — bukan data uji
   coba), dan menyiapkan symlink `public/storage`. Kalau hosting memblokir
   `symlink()`, aplikasi otomatis tetap melayani foto/gambar lewat PHP
   (`config/filesystems.php` disk `public` sudah diset `serve => true`) —
   tidak ada langkah manual tambahan.
5. **Langkah 4 — Cabang & Akun Admin**: buat Cabang pertama (Kantor Pusat)
   sekaligus akun Admin Sistem pertama dari satu form — menggantikan
   perintah `tinker` manual di §7 versi lama.
6. **Langkah 5 — Selesai**: aplikasi menjalankan `config:cache`/`route:cache`/
   `view:cache` otomatis, lalu menampilkan checklist sisa langkah yang
   **memang tidak bisa diotomatiskan** karena butuh menu cPanel:
   - 2 baris Cron Job (§9) — teks siap-tempel ditampilkan langsung di halaman.
   - Aktifkan SSL/AutoSSL (§10).
   - Login pertama kali, lalu aktifkan 2FA sungguhan (scan QR) dan ganti
     password — akun dari langkah 4 hanya melewati syarat 2FA untuk sekali
     login pertama, bukan pengganti 2FA asli.

Setelah langkah 5 selesai, `/install` otomatis terkunci (redirect ke
`/login`) — tidak bisa diakses ulang, jadi aman ditinggal tanpa dihapus.

> Kalau installer sendiri tidak bisa dibuka (mis. permission folder rusak
> total sampai halaman pertama pun error), lanjut ke instalasi manual di §5
> dan seterusnya di bawah — semua langkah yang di atas dijelaskan ulang
> versi manualnya lewat SSH/tinker, termasuk kenapa masing-masing dibutuhkan.

---

## 1. Persyaratan Hosting

Cek/aktifkan di cPanel sebelum mulai:

| Kebutuhan | Cara cek/set di cPanel |
|---|---|
| **PHP 8.3** (atau lebih baru, sesuai `composer.json`) | Menu **MultiPHP Manager** → pilih domain → PHP 8.3 |
| Ekstensi PHP: `openssl, pdo_mysql, mbstring, tokenizer, xml, ctype, json, bcmath, fileinfo, gd, zip, curl` | Menu **MultiPHP INI Editor** → tab *Extensions*, centang yang belum aktif |
| **MySQL 8** (atau MariaDB 10.6+) | Menu **MySQL Databases** |
| **SSH access** (sangat disarankan) | Menu **SSH Access** — tanyakan ke provider hosting jika tidak terlihat, beberapa paket shared hosting menyediakannya walau tidak dipromosikan |
| `upload_max_filesize` ≥ 8M dan `post_max_size` ≥ 12M | Menu **MultiPHP INI Editor** (atau file `.user.ini` di document root). Default banyak hosting hanya 2M — terlalu kecil untuk unggah gambar latar halaman login (batas aplikasi 4MB); unggahan akan ditolak diam-diam sebelum Laravel sempat memvalidasi |
| **Cron Jobs** | Menu **Cron Jobs** — hampir semua paket shared hosting punya ini |
| **Composer** | Biasanya sudah tersedia via SSH (`composer --version`). Jika tidak ada, build `vendor/` di komputer lokal lalu upload (lihat §3B) |

Yang **TIDAK** wajib ada di hosting:
- **Node.js/NPM** — tidak dipakai. Semua halaman admin/staf memakai CSS
  inline, bukan hasil build Vite/Tailwind, jadi `npm run build` tidak perlu
  dijalankan di server sama sekali.
- **Redis** — bagus kalau ada (banyak host premium menyediakan add-on
  Redis), tapi kalau tidak ada, aplikasi tetap bisa jalan penuh dengan
  konfigurasi fallback di §5.
- **Supervisor / proses background 24 jam** — pekerjaan antrian (export
  laporan, kirim notifikasi) dijalankan lewat Cron Job tiap menit (§7), bukan
  proses `queue:work` yang menyala terus — cocok untuk shared hosting yang
  memang tidak mengizinkan proses panjang.

---

## 2. Siapkan Database

1. cPanel → **MySQL Databases** → buat database baru, misalnya
   `namauser_koperasi`.
2. Buat MySQL user baru + password kuat, lalu **assign** user itu ke
   database tadi dengan privilese **ALL PRIVILEGES**.
3. Catat: nama database, username, password — dipakai di `.env` (§5).

---

## 3A. Upload Kode — Jalur SSH + Composer (disarankan)

1. Login SSH ke hosting.
2. Clone/upload kode ke folder **di luar** `public_html`, misalnya:
   ```
   /home/namauser/koperasi-app/
   ```
   (Jangan taruh langsung di `public_html` — lihat §4 kenapa.)
3. Masuk folder itu, install dependency produksi:
   ```bash
   cd /home/namauser/koperasi-app
   composer install --no-dev --optimize-autoloader
   ```

## 3B. Upload Kode — Tanpa SSH/Composer di server

1. Di komputer lokal (yang sudah punya PHP+Composer), jalankan:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   Ini akan mengisi folder `vendor/` secara lengkap.
2. Zip **seluruh folder proyek** (termasuk `vendor/` hasil build tadi).
3. Upload zip lewat cPanel **File Manager** ke folder di luar `public_html`
   (misalnya `/home/namauser/koperasi-app/`), lalu ekstrak di sana.

> Kedua jalur di atas menghasilkan struktur folder yang sama: proyek Laravel
> lengkap ada di `/home/namauser/koperasi-app/`, **bukan** di dalam
> `public_html`.

---

## 4. Arahkan Domain ke folder `public/`

Laravel butuh document root domain menunjuk ke folder `public/` di dalam
proyek, bukan ke akar proyek. Dua cara, pilih salah satu:

**Cara A — kalau host mengizinkan ubah document root (disarankan, lebih aman):**
cPanel → **Domains** → pilih domain/subdomain → ubah *Document Root* menjadi:
```
/home/namauser/koperasi-app/public
```

**Cara B — kalau host tidak mengizinkan ubah document root:**
1. Salin **isi** folder `public/` (bukan foldernya) ke `public_html/`.
2. Edit `public_html/index.php`, ubah 2 baris `require` supaya menunjuk ke
   lokasi proyek yang sebenarnya:
   ```php
   require __DIR__.'/../home/namauser/koperasi-app/vendor/autoload.php';
   $app = require_once __DIR__.'/../home/namauser/koperasi-app/bootstrap/app.php';
   ```
   (Sesuaikan path absolut dengan lokasi folder proyek Anda.)

Dengan cara ini, folder `app/`, `.env`, `database/`, dsb. tetap berada **di
luar** `public_html` dan tidak bisa diakses langsung lewat browser — ini
penting untuk keamanan (`.env` berisi password database).

---

## 5. Konfigurasi `.env`

> §5-§8 dan §11 di bawah ini dijalankan otomatis oleh wizard `/install` (§0)
> — bagian manual di bawah dipertahankan sebagai referensi/fallback SSH,
> tidak perlu diikuti kalau sudah lewat wizard. §9 (Cron Job) dan §10 (SSL)
> tetap manual lewat cPanel — di luar jangkauan aplikasi apa pun.

Di folder proyek (bukan `public_html`), salin `.env.example` menjadi `.env`,
lalu sesuaikan:

```env
APP_NAME="Koperasi Sejahtera Bersama"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainanda.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=namauser_koperasi
DB_USERNAME=namauser_dbuser
DB_PASSWORD=isi_password_kuat
```

**Wajib:** `APP_DEBUG=false` di produksi — kalau `true`, error apa pun akan
menampilkan stack trace lengkap (nama file, query SQL) ke pengunjung, celah
keamanan serius.

### Jika hosting TIDAK punya Redis
Ganti bagian session/cache/queue jadi:
```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```
(Tabel `sessions`, `cache`, `jobs` sudah otomatis dibuat lewat migrasi di
§6 — tidak perlu langkah tambahan.)

### Jika hosting PUNYA Redis
Biarkan `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`
seperti bawaan `.env.example`, lalu isi `REDIS_HOST`/`REDIS_PORT`/
`REDIS_PASSWORD` sesuai kredensial dari host.

### Email (wajib untuk reset password, notifikasi jatuh tempo, dsb.)
```env
MAIL_MAILER=smtp
MAIL_HOST=mail.domainanda.com
MAIL_PORT=587
MAIL_USERNAME=noreply@domainanda.com
MAIL_PASSWORD=isi_password_email
MAIL_FROM_ADDRESS="noreply@domainanda.com"
```
(Gunakan akun email yang dibuat lewat cPanel **Email Accounts**.)

### WhatsApp & Xendit (opsional)
Baris `WHATSAPP_*` dan `XENDIT_*` boleh dikosongkan kalau fitur itu belum
dipakai — aplikasi otomatis menyembunyikan tombol terkait/fallback ke email,
bukan error.

Setelah `.env` siap, generate application key (wajib, sekali saja):
```bash
php artisan key:generate
```

---

## 6. Migrasi Database & Data Master Awal

```bash
php artisan migrate --force
php artisan db:seed --force
```

`db:seed` di atas **aman untuk produksi** — hanya mengisi data master yang
memang dibutuhkan aplikasi untuk berjalan (Bagan Akun standar koperasi, daftar
role & permission, jenis anggota, template kartu anggota, dsb.), **tidak**
membuat akun percobaan atau transaksi dummy apa pun.

> ⚠️ **Jangan** menjalankan `DemoDataSeeder` di server produksi — seeder itu
> khusus untuk data uji coba di lingkungan pengembangan (berisi transaksi
> dummy dan akun dengan password contoh yang diketahui publik).

Setelah seed, tambahkan minimal satu **Cabang** (Kantor Pusat) — via `tinker`
karena belum ada UI CRUD Cabang:
```bash
php artisan tinker --execute="
App\Models\Branch::create([
    'code' => 'PST',
    'name' => 'Kantor Pusat',
    'address' => 'Alamat kantor pusat',
    'operational_date' => now(),
    'is_active' => true,
]);
"
```

---

## 7. Buat Akun Admin Pertama

Login internal (staf/pengurus) **wajib** mengaktifkan 2FA sebelum bisa
mengakses aplikasi — tapi tidak ada jalur pendaftaran mandiri untuk akun
internal, jadi akun admin pertama harus dibuat langsung lewat `tinker`,
sekaligus melewati (bootstrap) syarat 2FA untuk login pertama kali:

```bash
php artisan tinker --execute="
\$user = App\Models\User::create([
    'name' => 'Nama Admin',
    'email' => 'admin@domainanda.com',
    'password' => 'GantiPasswordIniSekarang123!',
    'is_active' => true,
]);
\$user->two_factor_confirmed_at = now();
\$user->save();
\$user->assignRole('admin_sistem');
App\Models\UserBranchScope::create(['user_id' => \$user->id, 'scope_type' => 'all']);
echo 'Admin dibuat: ' . \$user->email;
"
```

> **Kenapa `two_factor_confirmed_at` di-set terpisah, bukan lewat
> `create([...])`:** `User` model mendeklarasikan
> `#[Fillable(['name', 'email', 'password', 'is_active'])]` — kolom di luar
> daftar itu (termasuk `two_factor_confirmed_at`) sengaja tidak bisa diisi
> massal lewat `create()`/`update()` (supaya tidak ada jalur form biasa yang
> bisa menyelundupkan bypass 2FA). Kalau tetap dimasukkan ke `create([...])`,
> Laravel **membisu** membuang kolom itu tanpa error — akun admin akan
> tetap dibuat, tapi login pertama akan berhenti di pesan "Aktifkan
> autentikasi dua faktor (MFA)" karena kolomnya sebenarnya tidak pernah
> tersimpan.

Setelah bisa login, **segera**:
1. Buka **Profil Saya** → ganti password.
2. Buka pengaturan 2FA → aktifkan ulang dengan scan QR code aplikasi
   authenticator (Google Authenticator/Authy) yang sesungguhnya — perintah di
   atas hanya melewati syarat untuk login pertama, bukan pengganti 2FA asli.

Buat akun untuk role lain (teller, manajer, dst.) lewat menu **Manajemen
Pengguna** setelah login sebagai admin — tidak perlu tinker lagi.

---

## 8. Storage Symlink (foto anggota, logo, gambar barang)

```bash
php artisan storage:link
```

Kalau SSH tidak tersedia dan perintah di atas tidak bisa dijalankan, buat
file kecil `public_html/make-symlink.php` isinya:
```php
<?php
symlink(__DIR__.'/../home/namauser/koperasi-app/storage/app/public', __DIR__.'/storage');
echo 'Symlink dibuat.';
```
Akses sekali lewat browser (`https://domainanda.com/make-symlink.php`), lalu
**hapus file ini segera setelah berhasil** (jangan dibiarkan menggantung —
siapa pun yang tahu URL-nya bisa menjalankannya ulang).

---

## 9. Cron Job (scheduler + antrian)

cPanel → **Cron Jobs** → tambahkan **satu** baris ini, jadwal *Every Minute*:

```
* * * * * cd /home/namauser/koperasi-app && php artisan schedule:run >> /dev/null 2>&1
```

Baris ini menjalankan **semua** tugas terjadwal aplikasi (penyusutan aktiva
bulanan, pengingat jatuh tempo angsuran harian, dll.) — sudah cukup satu
baris, jangan tambah cron terpisah per tugas.

**Antrian (queue) tanpa proses 24 jam:** karena shared hosting tidak
mengizinkan proses seperti `php artisan queue:work` menyala selamanya (dan
**Laravel Horizon tidak bisa dipakai di sini** — Horizon butuh ekstensi PHP
`pcntl` yang hampir tidak pernah tersedia di shared hosting), tambahkan cron
job **kedua**, jadwal *Every Minute* juga:

```
* * * * * cd /home/namauser/koperasi-app && php artisan queue:work --stop-when-empty --tries=3 --max-time=50 >> /dev/null 2>&1
```

Ini menyalakan worker singkat tiap menit, memproses semua pekerjaan yang
menunggu (export laporan keuangan, kirim notifikasi WhatsApp/email), lalu
berhenti sendiri (`--stop-when-empty`) — aman untuk batasan proses shared
hosting, dan cukup responsif (jeda maksimal ±1 menit).

---

## 10. SSL/HTTPS

cPanel → **SSL/TLS Status** → aktifkan **AutoSSL** (gratis, Let's Encrypt)
untuk domain. Setelah aktif, pastikan `APP_URL` di `.env` memakai `https://`.

---

## 11. Optimasi Produksi (jalankan setelah semua `.env` final)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> ⚠️ Kalau nanti mengubah `.env` lagi, wajib jalankan
> `php artisan config:clear` dulu sebelum `config:cache` ulang — kalau tidak,
> perubahan `.env` tidak akan terbaca (nilai lama tetap ter-cache).

---

## 12. Verifikasi Instalasi

1. Buka `https://domainanda.com/up` — harus menampilkan halaman kosong status
   200 (health check bawaan Laravel; kalau muncul error, cek log di
   `storage/logs/laravel.log`).
2. Buka `https://domainanda.com/login`, login pakai akun admin dari §7.
3. Login pertama akan diminta setup 2FA asli — scan QR, simpan recovery
   codes di tempat aman.
4. Cek dashboard utama tampil dengan benar (bukan halaman error).
5. Coba upload foto (mis. buat anggota baru dengan foto) — pastikan gambar
   tampil setelah disimpan (memverifikasi symlink storage di §8 berhasil).
6. Buka menu **Laporan** → **Laporan Persediaan/Kartu Persediaan** — pastikan
   tampil tanpa error (memverifikasi koneksi database & migrasi lengkap).
7. Kalau memakai fitur Xendit atau WhatsApp, uji satu transaksi kecil di mode
   sandbox/test terlebih dahulu sebelum dipakai transaksi sungguhan.

---

## 13. Troubleshooting Umum

| Gejala | Penyebab & Solusi |
|---|---|
| Halaman putih blank / 500 error | Cek `storage/logs/laravel.log`. Biasanya `APP_KEY` belum di-generate, atau permission folder `storage/`+`bootstrap/cache/` belum bisa ditulis (`chmod -R 775 storage bootstrap/cache`, atau via File Manager set permission 755/775 sesuai user hosting). |
| Foto/gambar tidak muncul (ikon patah) | Symlink storage (§8) belum dibuat, atau salah path. |
| Export PDF/Excel macet di status "Menunggu" terus | Cron job kedua di §9 (`queue:work`) belum aktif, atau salah path folder di baris cron. |
| Perubahan `.env` tidak berpengaruh setelah edit | Lupa `php artisan config:clear` setelah edit `.env` (kalau sudah pernah `config:cache`). |
| Error `.env` / password DB terlihat di browser saat error | `APP_DEBUG` masih `true` — segera ubah ke `false` dan `config:cache` ulang. |
| Unggah gambar latar login gagal/halaman kembali kosong tanpa pesan | `upload_max_filesize`/`post_max_size` PHP lebih kecil dari berkasnya (§1). Naikkan lewat MultiPHP INI Editor, atau perkecil gambarnya. |
| Notifikasi WhatsApp gagal terus | Normal kalau `WHATSAPP_API_TOKEN` kosong — otomatis fallback ke email, bukan bug. Isi token asli dari Meta Business kalau sudah siap pakai. |
| Login gagal terus untuk akun staf baru dengan pesan "Aktifkan autentikasi dua faktor" | Memang perilaku yang benar (bukan bug) — akun internal (teller, manajer, dst.) wajib setup 2FA di percobaan login pertama sebelum bisa masuk. |
| Buka domain malah selalu redirect ke `/install` | Normal selama `storage/installed` belum ada — berarti wizard belum diselesaikan sampai Langkah 5. Lanjutkan wizard-nya; kalau memang sudah pernah selesai tapi file itu terhapus (mis. saat re-deploy menimpa folder `storage/`), install ulang tidak akan merusak data (migrasi/seed aman dijalankan ulang) — cukup selesaikan lagi sampai Langkah 5 supaya lock file dibuat ulang. |
| Halaman `/install` sendiri error 500 sebelum tampil apa pun | Folder `storage/framework/sessions` belum bisa ditulis — installer butuh session file untuk CSRF/form, jadi ini satu-satunya langkah yang tetap butuh permission benar sebelum wizard bisa dibuka sama sekali (`chmod -R 775 storage bootstrap/cache`). |
| Tombol "Lanjutkan" di Langkah 1 installer tidak bisa diklik | Ada baris "Gagal" (bukan "Perhatian") di tabel requirement — biasanya ekstensi PHP wajib belum aktif. Aktifkan lewat MultiPHP INI Editor lalu muat ulang halaman, tidak perlu mulai dari awal. |

---

## Ringkasan Checklist

Baris bertanda **(auto)** dikerjakan otomatis oleh wizard `/install` (§0) —
sisanya tetap manual lewat cPanel karena di luar jangkauan aplikasi.

- [ ] PHP 8.3 + ekstensi lengkap aktif
- [ ] Database MySQL + user dibuat
- [ ] Kode ter-upload, `composer install --no-dev` selesai
- [ ] Document root domain menunjuk ke `public/`
- [ ] *(auto)* `.env` terisi lengkap, `APP_DEBUG=false`, `APP_KEY` sudah di-generate
- [ ] *(auto)* `php artisan migrate --force` + `php artisan db:seed --force` sukses
- [ ] *(auto)* Minimal 1 Cabang dibuat
- [ ] *(auto)* Akun admin pertama dibuat — 2FA asli tetap perlu di-setup manual saat login pertama
- [ ] *(auto)* `php artisan storage:link` sukses (atau fallback `serve` otomatis kalau symlink diblok hosting), foto/gambar tampil
- [ ] 2 Cron Job aktif (`schedule:run` + `queue:work --stop-when-empty`) — teks siap-tempel ada di Langkah 5 wizard
- [ ] SSL aktif, `APP_URL` pakai `https://`
- [ ] *(auto)* `config:cache`/`route:cache`/`view:cache` dijalankan
- [ ] `/up` menampilkan status sehat

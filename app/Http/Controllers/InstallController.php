<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Support\Installer\EnvFileWriter;
use App\Support\Installer\InstallerPage;
use App\Support\Installer\InstallState;
use App\Support\Installer\RequirementsChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use PDO;
use PDOException;
use Predis\Client as PredisClient;
use Throwable;

/**
 * Web installer untuk deploy di shared hosting (PANDUAN_INSTALASI_SHARED_HOSTING.md)
 * — mengotomatiskan §1-§9 dan §11 dari panduan itu (cek requirement, tulis
 * .env, migrate+seed, buat Cabang pertama, buat akun admin pertama + bootstrap
 * MFA, storage:link dengan fallback, optimasi cache) supaya tidak perlu SSH/
 * tinker sama sekali. §4 (document root), §9 cron, dan §10 SSL tetap manual
 * lewat cPanel — di luar jangkauan kode aplikasi.
 *
 * Sengaja tidak pakai Blade/view() di seluruh controller ini — lihat
 * docblock InstallerPage.
 */
class InstallController extends Controller
{
    public function welcome(): Response
    {
        $checks = RequirementsChecker::check();
        $passes = RequirementsChecker::passes();

        $rows = '';

        foreach ($checks as $check) {
            if ($check['ok']) {
                [$badgeClass, $badgeText] = ['badge-ok', 'OK'];
            } elseif ($check['blocking']) {
                [$badgeClass, $badgeText] = ['badge-fail', 'Gagal'];
            } else {
                [$badgeClass, $badgeText] = ['badge-warn', 'Perhatian'];
            }

            $rows .= '<tr><td>'.e($check['label']).'</td><td class="muted">'.e($check['detail']).'</td>'
                .'<td><span class="badge '.$badgeClass.'">'.$badgeText.'</span></td></tr>';
        }

        $button = $passes
            ? '<a class="btn-primary" href="'.e(route('install.database')).'">Lanjutkan</a>'
            : '<span class="btn-primary disabled">Lengkapi item "Gagal" dulu, lalu muat ulang halaman ini</span>';

        $body = <<<HTML
<p class="lead">Cek kesiapan server sebelum mulai. Baris berstatus <strong>Gagal</strong> wajib dibereskan dulu (biasanya lewat menu MultiPHP INI Editor / File Manager permission di cPanel) — baris <strong>Perhatian</strong> boleh dilewati dulu, hanya memengaruhi sebagian fitur.</p>
<table class="checks">
    <thead><tr><th>Item</th><th>Detail</th><th>Status</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<div class="btn-row">{$button}</div>
HTML;

        return response(InstallerPage::render('welcome', 'Langkah 1 — Persyaratan Server', $body));
    }

    public function showDatabase(Request $request): Response
    {
        $error = $this->flashedError();

        $values = [
            'app_name' => old('app_name', EnvFileWriter::get('APP_NAME') ?: (config('app.name') ?: 'Koperasi')),
            'app_url' => old('app_url', EnvFileWriter::get('APP_URL') ?: $request->getSchemeAndHttpHost()),
            'db_host' => old('db_host', EnvFileWriter::get('DB_HOST') ?: '127.0.0.1'),
            'db_port' => old('db_port', EnvFileWriter::get('DB_PORT') ?: '3306'),
            'db_database' => old('db_database', EnvFileWriter::get('DB_DATABASE') ?: ''),
            'db_username' => old('db_username', EnvFileWriter::get('DB_USERNAME') ?: ''),
            'redis_host' => old('redis_host', EnvFileWriter::get('REDIS_HOST') ?: '127.0.0.1'),
            'redis_port' => old('redis_port', EnvFileWriter::get('REDIS_PORT') ?: '6379'),
        ];

        $action = e(route('install.database.store'));
        $csrf = InstallerPage::csrfField();
        $alert = InstallerPage::alert($error);

        $fApp = InstallerPage::field('Nama Aplikasi', 'app_name', 'text', $values['app_name']);
        $fUrl = InstallerPage::field('URL Aplikasi', 'app_url', 'url', $values['app_url'], true, 'Termasuk https://, tanpa garis miring di akhir');
        $fHost = InstallerPage::field('Host Database', 'db_host', 'text', $values['db_host']);
        $fPort = InstallerPage::field('Port', 'db_port', 'text', $values['db_port']);
        $fName = InstallerPage::field('Nama Database', 'db_database', 'text', $values['db_database'], true, 'Buat lebih dulu lewat menu MySQL Databases di cPanel');
        $fUser = InstallerPage::field('User Database', 'db_username', 'text', $values['db_username']);
        $fPass = InstallerPage::field('Password Database', 'db_password', 'password', '', false);
        $fRedisHost = InstallerPage::field('Redis Host', 'redis_host', 'text', $values['redis_host'], false);
        $fRedisPort = InstallerPage::field('Redis Port', 'redis_port', 'text', $values['redis_port'], false);
        $fRedisPass = InstallerPage::field('Redis Password', 'redis_password', 'password', '', false);

        $body = <<<HTML
{$alert}
<p class="lead">Data ini ditulis langsung ke file <code>.env</code>. Koneksi database akan dites dulu sebelum disimpan.</p>
<form method="POST" action="{$action}">
    {$csrf}
    <fieldset>
        <legend>Aplikasi</legend>
        {$fApp}
        {$fUrl}
    </fieldset>
    <fieldset>
        <legend>Database MySQL</legend>
        <div class="grid-2">{$fHost}{$fPort}</div>
        {$fName}
        <div class="grid-2">{$fUser}{$fPass}</div>
    </fieldset>
    <fieldset>
        <legend>Redis (opsional)</legend>
        <p class="lead" style="margin-top:0">Kosongkan kalau hosting tidak menyediakan Redis — aplikasi otomatis mendeteksi ketersediaannya dan beralih ke session/cache berbasis database bila tidak terjangkau, tidak perlu diisi manual.</p>
        <div class="grid-2">{$fRedisHost}{$fRedisPort}</div>
        {$fRedisPass}
    </fieldset>
    <div class="btn-row"><button type="submit" class="btn-primary">Tes Koneksi &amp; Simpan</button></div>
</form>
HTML;

        return response(InstallerPage::render('database', 'Langkah 2 — Aplikasi &amp; Database', $body));
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_host' => ['required', 'string', 'max:150'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:64'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'redis_host' => ['nullable', 'string', 'max:150'],
            'redis_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'redis_password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            new PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4",
                $data['db_username'],
                $data['db_password'] ?? '',
                [PDO::ATTR_TIMEOUT => 5],
            );
        } catch (PDOException $e) {
            return redirect()->route('install.database')->withInput()
                ->with('installer_error', 'Koneksi database gagal: '.$e->getMessage());
        }

        $redisHost = $data['redis_host'] ?? '127.0.0.1';
        $redisPort = (int) ($data['redis_port'] ?? 6379);
        $redisPassword = $data['redis_password'] ?? null;
        $redisAvailable = $this->redisReachable($redisHost, $redisPort, $redisPassword);

        EnvFileWriter::set([
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
            'SESSION_DRIVER' => $redisAvailable ? 'redis' : 'database',
            'CACHE_STORE' => $redisAvailable ? 'redis' : 'database',
            'QUEUE_CONNECTION' => $redisAvailable ? 'redis' : 'database',
            'REDIS_HOST' => $redisHost,
            'REDIS_PORT' => (string) $redisPort,
            'REDIS_PASSWORD' => $redisPassword ?? 'null',
        ]);

        return redirect()->route('install.migrate')
            ->with('installer_redis_detected', $redisAvailable);
    }

    public function showMigrate(): Response
    {
        $alert = InstallerPage::alert($this->flashedError());
        $redisDetected = session('installer_redis_detected');

        $redisNote = $redisDetected === null
            ? ''
            : ($redisDetected
                ? '<div class="alert alert-success">Redis terdeteksi &amp; dipakai untuk session/cache/queue.</div>'
                : '<div class="alert alert-error" style="background:#F3E6CC;color:#8A5A18;border-color:#E4B15C">Redis tidak terjangkau — otomatis dialihkan ke session/cache/queue berbasis database (tabel dibuat pada langkah ini juga, tidak perlu langkah tambahan).</div>');

        $action = e(route('install.migrate.run'));
        $csrf = InstallerPage::csrfField();

        $body = <<<HTML
{$alert}
{$redisNote}
<p class="lead">Langkah ini menjalankan migrasi seluruh tabel, mengisi data master (bagan akun standar, role &amp; permission, jenis anggota, dst — bukan data uji coba), dan menyiapkan folder penyimpanan foto/gambar.</p>
<ul class="checklist">
    <li>Migrasi seluruh tabel database</li>
    <li>Seed data master yang dibutuhkan aplikasi untuk berjalan</li>
    <li>Symlink <code>public/storage</code> &rarr; <code>storage/app/public</code> (otomatis pakai jalur cadangan kalau <code>symlink()</code> diblok hosting)</li>
</ul>
<form method="POST" action="{$action}">
    {$csrf}
    <div class="btn-row"><button type="submit" class="btn-primary">Jalankan Migrasi</button></div>
</form>
HTML;

        return response(InstallerPage::render('migrate', 'Langkah 3 — Migrasi &amp; Storage', $body));
    }

    public function runMigrate(): RedirectResponse
    {
        // Shared hosting commonly caps max_execution_time at 30-60s
        // (PANDUAN_INSTALASI_SHARED_HOSTING.md §1) — migrate+seed on the
        // full schema can run past that under cold disk/DB caches, which
        // would otherwise kill this request mid-migration and leave the
        // database half-built. set_time_limit() is itself sometimes
        // disabled on shared hosting, so this is a best-effort extension,
        // not a guarantee.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
        } catch (Throwable $e) {
            return redirect()->route('install.migrate')
                ->with('installer_error', 'Migrasi gagal: '.$e->getMessage());
        }

        $this->linkStorage();

        return redirect()->route('install.admin');
    }

    public function showAdmin(): Response
    {
        $error = $this->flashedError();

        $values = [
            'branch_code' => old('branch_code', 'PST'),
            'branch_name' => old('branch_name', 'Kantor Pusat'),
            'branch_address' => old('branch_address', ''),
            'admin_name' => old('admin_name', ''),
            'admin_email' => old('admin_email', ''),
        ];

        $action = e(route('install.admin.store'));
        $csrf = InstallerPage::csrfField();
        $alert = InstallerPage::alert($error);

        $fCode = InstallerPage::field('Kode Cabang', 'branch_code', 'text', $values['branch_code']);
        $fBranchName = InstallerPage::field('Nama Cabang', 'branch_name', 'text', $values['branch_name']);
        $fAddress = InstallerPage::field('Alamat', 'branch_address', 'text', $values['branch_address'], false);
        $fAdminName = InstallerPage::field('Nama Lengkap', 'admin_name', 'text', $values['admin_name']);
        $fAdminEmail = InstallerPage::field('Email', 'admin_email', 'email', $values['admin_email']);
        $fPassword = InstallerPage::field('Password', 'admin_password', 'password', '', true, 'Minimal 10 karakter, kombinasi huruf besar/kecil & angka');
        $fPasswordConfirm = InstallerPage::field('Ulangi Password', 'admin_password_confirmation', 'password', '');

        $body = <<<HTML
{$alert}
<p class="lead">Cabang pertama (Kantor Pusat) dan akun Admin Sistem pertama dibuat sekaligus di sini. Akun ini otomatis aktif dan melewati syarat 2FA untuk login pertama kali saja — setelah masuk, segera aktifkan 2FA sungguhan lewat menu Profil.</p>
<form method="POST" action="{$action}">
    {$csrf}
    <fieldset>
        <legend>Cabang Pertama</legend>
        <div class="grid-2">{$fCode}{$fBranchName}</div>
        {$fAddress}
    </fieldset>
    <fieldset>
        <legend>Akun Admin Sistem</legend>
        {$fAdminName}
        {$fAdminEmail}
        <div class="grid-2">{$fPassword}{$fPasswordConfirm}</div>
    </fieldset>
    <div class="btn-row"><button type="submit" class="btn-primary">Buat Cabang &amp; Akun Admin</button></div>
</form>
HTML;

        return response(InstallerPage::render('admin', 'Langkah 4 — Cabang &amp; Akun Admin Pertama', $body));
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_code' => ['required', 'string', 'max:20'],
            'branch_name' => ['required', 'string', 'max:150'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'admin_password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        try {
            DB::transaction(function () use ($data): void {
                Branch::query()->firstOrCreate(
                    ['code' => $data['branch_code']],
                    [
                        'name' => $data['branch_name'],
                        'address' => $data['branch_address'] ?? null,
                        'operational_date' => now(),
                        'is_active' => true,
                    ],
                );

                $user = User::query()->create([
                    'name' => $data['admin_name'],
                    'email' => $data['admin_email'],
                    'password' => $data['admin_password'],
                    'is_active' => true,
                ]);

                // two_factor_confirmed_at is deliberately absent from User's
                // #[Fillable(...)] list (app/Models/User.php) so it can never
                // be mass-assigned through a normal form — create() above
                // would silently drop it. Setting it directly is the
                // intended escape hatch for this one-time bootstrap (mirrors
                // PANDUAN_INSTALASI_SHARED_HOSTING.md §7's tinker snippet):
                // lets the very first admin log in before real 2FA exists,
                // without weakening EnsureMfaIsEnabledForInternalRoles for
                // anyone else.
                $user->two_factor_confirmed_at = now();
                $user->save();

                $user->assignRole('admin_sistem');

                UserBranchScope::query()->create([
                    'user_id' => $user->id,
                    'scope_type' => 'all',
                ]);
            });
        } catch (Throwable $e) {
            return redirect()->route('install.admin')->withInput()
                ->with('installer_error', 'Gagal membuat cabang/akun admin: '.$e->getMessage());
        }

        return redirect()->route('install.finish');
    }

    public function finish(): Response
    {
        // config:cache reloads .env from a fresh Application boot (not from
        // this request's already-resolved config()), so it's the actual
        // on-disk value that gets baked into bootstrap/cache/config.php. If
        // that happened to be blank at this exact moment, every request
        // from here on would inherit a cached empty APP_KEY — permanently,
        // since BootstrapInstallerEnvironment's self-heal only runs while
        // InstallState::isInstalled() is false. One last guaranteed-fresh
        // check right before caching closes that gap for good.
        if (blank(EnvFileWriter::get('APP_KEY'))) {
            EnvFileWriter::set(['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        $cacheError = null;

        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (Throwable $e) {
            $cacheError = $e->getMessage();
        }

        InstallState::markInstalled();

        $warn = $cacheError
            ? InstallerPage::alert('Optimasi cache gagal dijalankan otomatis ('.$cacheError.') — instalasi tetap berhasil. Jalankan manual nanti lewat SSH: php artisan config:cache && php artisan route:cache && php artisan view:cache')
            : '';

        $cronBlock = e('cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1')
            ."\n".e('cd '.base_path().' && php artisan queue:work --stop-when-empty --tries=3 --max-time=50 >> /dev/null 2>&1');

        $loginUrl = e(route('login'));

        $body = <<<HTML
{$warn}
<div class="alert alert-success">Instalasi selesai — aplikasi sudah siap dipakai.</div>
<p class="lead">Langkah berikut ada di luar jangkauan aplikasi (perlu akses menu cPanel), selesaikan sebelum benar-benar dipakai produksi:</p>
<ul class="checklist">
    <li>Tambahkan 2 baris Cron Job berikut (menu <strong>Cron Jobs</strong>, jadwal <em>Every Minute</em>):</li>
</ul>
<pre>{$cronBlock}</pre>
<ul class="checklist">
    <li>Aktifkan AutoSSL/HTTPS lewat menu <strong>SSL/TLS Status</strong></li>
    <li>Setelah login pertama, aktifkan 2FA sungguhan (scan QR) lalu ganti password</li>
</ul>
<div class="btn-row"><a class="btn-primary" href="{$loginUrl}">Masuk ke Aplikasi &rarr;</a></div>
HTML;

        return response(InstallerPage::render('finish', 'Langkah 5 — Selesai', $body));
    }

    private function flashedError(): ?string
    {
        if ($installerError = session('installer_error')) {
            return $installerError;
        }

        $errors = session('errors');

        if ($errors && $errors->any()) {
            return $errors->first();
        }

        return null;
    }

    private function redisReachable(string $host, int $port, ?string $password): bool
    {
        try {
            $client = new PredisClient(array_filter([
                'host' => $host,
                'port' => $port,
                'password' => $password,
                'timeout' => 2.0,
            ], fn ($value) => $value !== null));

            $client->connect();
            $client->ping();
            $client->disconnect();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function linkStorage(): void
    {
        if (file_exists(public_path('storage'))) {
            return;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
            // Diamkan dengan sengaja: symlink() sering diblok shared hosting.
            // config/filesystems.php 'public' disk sudah 'serve' => true,
            // jadi Illuminate\Filesystem\ServeFile tetap melayani
            // storage/app/public/* lewat PHP walau symlink tidak ada —
            // tidak ada langkah manual tambahan yang perlu dijalankan.
        }
    }
}

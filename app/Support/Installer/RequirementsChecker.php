<?php

namespace App\Support\Installer;

/**
 * Cermin dari PANDUAN_INSTALASI_SHARED_HOSTING.md §1 (persyaratan hosting)
 * — dibaca ulang di sini supaya kalau constraint PHP/ekstensi di
 * composer.json berubah, cek ini gampang disamakan.
 */
class RequirementsChecker
{
    private const MIN_PHP_VERSION = '8.3.0';

    /** Wajib — kalau salah satu gagal, tombol "Lanjutkan" di halaman welcome diblok. */
    private const REQUIRED_EXTENSIONS = [
        'openssl', 'pdo_mysql', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath',
    ];

    /** Disarankan — dipakai fitur tertentu (PDF, barcode, upload foto), tapi tidak menghalangi instalasi. */
    private const RECOMMENDED_EXTENSIONS = [
        'fileinfo', 'gd', 'zip', 'curl',
    ];

    /**
     * @return array<int, array{label: string, detail: string, ok: bool, blocking: bool}>
     */
    public static function check(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'Versi PHP ('.self::MIN_PHP_VERSION.' atau lebih baru)',
            'detail' => 'Terpasang: PHP '.PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>='),
            'blocking' => true,
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => "Ekstensi PHP: {$extension}",
                'detail' => extension_loaded($extension)
                    ? 'Aktif'
                    : 'Tidak aktif — aktifkan lewat menu MultiPHP INI Editor di cPanel',
                'ok' => extension_loaded($extension),
                'blocking' => true,
            ];
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => "Ekstensi PHP (disarankan): {$extension}",
                'detail' => extension_loaded($extension)
                    ? 'Aktif'
                    : 'Tidak aktif — sebagian fitur (cetak PDF/barcode/upload foto) butuh ini, bisa diaktifkan belakangan',
                'ok' => extension_loaded($extension),
                'blocking' => false,
            ];
        }

        foreach (self::writablePaths() as $label => $path) {
            $checks[] = [
                'label' => "Folder bisa ditulis: {$label}",
                'detail' => $path,
                'ok' => self::isPathWritable($path),
                'blocking' => true,
            ];
        }

        $checks[] = [
            'label' => 'File .env bisa dibuat/ditulis',
            'detail' => EnvFileWriter::exists() ? EnvFileWriter::path() : base_path().' (belum ada, akan dibuat dari .env.example)',
            'ok' => EnvFileWriter::isWritable(),
            'blocking' => true,
        ];

        return $checks;
    }

    public static function passes(): bool
    {
        foreach (self::check() as $check) {
            if ($check['blocking'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private static function writablePaths(): array
    {
        return [
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/app/public' => storage_path('app/public'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
    }

    private static function isPathWritable(string $path): bool
    {
        return is_dir($path) ? is_writable($path) : is_writable(dirname($path));
    }
}

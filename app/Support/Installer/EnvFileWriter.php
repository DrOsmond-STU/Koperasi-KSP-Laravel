<?php

namespace App\Support\Installer;

/**
 * Baca/tulis `.env` langsung sebagai teks — dipakai installer sebelum
 * framework selesai boot dengan config final, jadi tidak bisa mengandalkan
 * config()/env() (itu snapshot dari .env versi LAMA di request yang sama).
 * Baris lain (komentar, urutan, blok yang belum diisi) dipertahankan apa
 * adanya; hanya key yang diminta yang diubah/ditambahkan.
 */
class EnvFileWriter
{
    public static function path(): string
    {
        return base_path('.env');
    }

    public static function exists(): bool
    {
        return file_exists(self::path());
    }

    public static function ensureExists(): void
    {
        if (self::exists()) {
            return;
        }

        $example = base_path('.env.example');

        file_put_contents(
            self::path(),
            file_exists($example) ? file_get_contents($example) : '',
        );
    }

    public static function isWritable(): bool
    {
        return self::exists() ? is_writable(self::path()) : is_writable(base_path());
    }

    public static function get(string $key): ?string
    {
        if (! self::exists()) {
            return null;
        }

        foreach (file(self::path(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/', $line, $matches) === 1) {
                return trim($matches[1], "\"'");
            }
        }

        return null;
    }

    /**
     * Read-modify-write under an exclusive lock, held from the initial read
     * through the final write. Shared hosting typically serves the
     * installer via PHP-FPM (multiple concurrent workers, unlike a
     * single-threaded `php artisan serve`), so a double-submitted form or a
     * reload-while-migrating can genuinely send two of these in parallel —
     * without the lock, the second writer's in-memory copy of $lines (read
     * before the first writer's save) would overwrite the first writer's
     * change when it saves, silently reverting it.
     *
     * @param  array<string, string|null>  $pairs
     */
    public static function set(array $pairs): void
    {
        self::ensureExists();

        $handle = fopen(self::path(), 'c+');

        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);

            $contents = stream_get_contents($handle);
            $lines = $contents === false || $contents === '' ? [] : preg_split('/\r\n|\r|\n/', rtrim($contents, "\r\n"));
            $remaining = $pairs;

            foreach ($lines as $index => $line) {
                foreach ($remaining as $key => $value) {
                    if (preg_match('/^'.preg_quote($key, '/').'=/', $line) === 1) {
                        $lines[$index] = $key.'='.self::formatValue($value);
                        unset($remaining[$key]);

                        break;
                    }
                }
            }

            foreach ($remaining as $key => $value) {
                $lines[] = $key.'='.self::formatValue($value);
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, implode(PHP_EOL, $lines).PHP_EOL);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function formatValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}

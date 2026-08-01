<?php

namespace App\Support\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Single source of truth for "sudah terinstal atau belum". Sengaja file
 * biasa (bukan baris di .env atau tabel DB) — harus bisa dibaca sebelum
 * .env/DB tentu ada isinya, dan harus tetap bisa dibaca kalau DB down
 * setelah instalasi (supaya app tidak tiba-tiba "kembali" ke installer).
 */
class InstallState
{
    public static function lockPath(): string
    {
        return storage_path('installed');
    }

    public static function isInstalled(): bool
    {
        if (file_exists(self::lockPath())) {
            return true;
        }

        // Upgrade path: a deployment that was already set up before this
        // installer existed (this app's own history — every environment
        // that currently runs it predates this feature — or one set up
        // via the old manual/tinker flow in the guide) has no lock file
        // yet. Without this check, BootstrapInstallerEnvironment would
        // start redirecting its every request to /install the moment this
        // code ships, even though nothing about the running app changed.
        // A real users row is a reasonable proxy for "setup already went
        // past the admin-creation step" — self-heals the lock file so this
        // DB check only ever runs once.
        if (self::hasExistingInstallation()) {
            self::markInstalled();

            return true;
        }

        return false;
    }

    public static function markInstalled(): void
    {
        file_put_contents(
            self::lockPath(),
            'Terinstal pada '.now()->toDateTimeString().PHP_EOL,
        );
    }

    private static function hasExistingInstallation(): bool
    {
        try {
            return Schema::hasTable('users') && DB::table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Cadangan penuh basis data — dijalankan SEBELUM koreksi data apa pun
 * (RUNBOOK §5.6: koreksi data produksi selalu didahului cadangan yang sudah
 * diverifikasi, bukan sekadar dibuat).
 *
 * Kata sandi basis data tidak pernah muncul pada baris perintah — ia ditulis
 * ke berkas --defaults-file sementara ber-mode 0600, karena argumen proses
 * dapat dibaca pengguna lain melalui /proc pada hosting bersama.
 *
 * Keluaran diverifikasi tiga lapis sebelum dinyatakan berhasil: kode keluar
 * mysqldump, penanda "Dump completed" di ekor berkas, dan jumlah CREATE TABLE
 * dibandingkan jumlah tabel yang benar-benar ada. Cadangan yang terpotong di
 * tengah (kuota disk habis, koneksi putus) lolos dari pemeriksaan kode keluar
 * saja, dan itulah kegagalan yang paling berbahaya di sini.
 */
#[Signature('db:cadangkan
    {--dir= : Direktori tujuan, default storage/app/cadangan}
    {--label=manual : Label singkat yang ikut ke nama berkas}
    {--tanpa-kompresi : Simpan .sql apa adanya, jangan di-gzip}')]
#[Description('Membuat cadangan penuh basis data (mysqldump) lalu memverifikasi keutuhannya')]
class CadangkanBasisData extends Command
{
    public function handle(): int
    {
        $koneksi = config('database.default');
        $db = config("database.connections.{$koneksi}");

        if (($db['driver'] ?? null) !== 'mysql') {
            $this->error("Perintah ini hanya untuk driver mysql, bukan \"{$db['driver']}\".");

            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: storage_path('app/cadangan');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Gagal membuat direktori {$dir}.");

            return self::FAILURE;
        }

        $label = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $this->option('label')) ?: 'manual';
        $berkas = sprintf('%s/%s_%s_%s.sql', rtrim($dir, '/'), $db['database'], now()->format('Ymd-His'), $label);

        $defaults = $this->tulisDefaultsFile($dir, $db);

        try {
            $this->info("Mencadangkan {$db['database']} ke {$berkas} ...");

            $proses = new Process([
                'mysqldump',
                '--defaults-file='.$defaults,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                $db['database'],
            ]);
            $proses->setTimeout(3600);

            $keluaran = fopen($berkas, 'wb');
            $proses->run(function (string $jenis, string $data) use ($keluaran): void {
                if ($jenis === Process::OUT) {
                    fwrite($keluaran, $data);
                }
            });
            fclose($keluaran);

            if (! $proses->isSuccessful()) {
                @unlink($berkas);
                $this->error('mysqldump gagal: '.trim($proses->getErrorOutput()));

                return self::FAILURE;
            }
        } finally {
            @unlink($defaults);
        }

        $jumlahTabel = count($this->daftarTabel());

        if (($galat = $this->periksaKeutuhan($berkas, $jumlahTabel)) !== null) {
            @unlink($berkas);
            $this->error($galat);

            return self::FAILURE;
        }

        $mentah = filesize($berkas);

        if (! $this->option('tanpa-kompresi')) {
            $berkas = $this->kompres($berkas);
        }

        $this->newLine();
        $this->info('Cadangan selesai dan terverifikasi.');
        $this->table(['Keterangan', 'Nilai'], [
            ['Berkas', $berkas],
            ['Ukuran mentah', number_format($mentah).' byte'],
            ['Ukuran tersimpan', number_format((int) filesize($berkas)).' byte'],
            ['SHA-256', hash_file('sha256', $berkas)],
            ['Jumlah tabel', (string) $jumlahTabel],
        ]);

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function daftarTabel(): array
    {
        return array_map(
            fn (object $baris) => (string) array_values((array) $baris)[0],
            DB::select('SHOW TABLES'),
        );
    }

    /**
     * @param  array<string, mixed>  $db
     */
    private function tulisDefaultsFile(string $dir, array $db): string
    {
        $berkas = tempnam($dir, '.mycnf');
        file_put_contents($berkas, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=\"%s\"\n",
            $db['host'],
            $db['port'],
            $db['username'],
            str_replace(['\\', '"'], ['\\\\', '\"'], (string) $db['password']),
        ));
        chmod($berkas, 0600);

        return $berkas;
    }

    /**
     * mysqldump menutup berkasnya dengan baris "-- Dump completed on ...".
     * Ketiadaan baris itu berarti proses berhenti di tengah jalan meskipun
     * kode keluarnya nol — misalnya karena kuota disk habis saat menulis.
     */
    private function periksaKeutuhan(string $berkas, int $jumlahTabel): ?string
    {
        if (! is_file($berkas) || filesize($berkas) < 1024) {
            return 'Berkas cadangan kosong atau terlalu kecil.';
        }

        $ekor = (string) shell_exec('tail -c 512 '.escapeshellarg($berkas));
        if (! str_contains($ekor, 'Dump completed')) {
            return 'Cadangan terpotong — penanda "Dump completed" tidak ditemukan di akhir berkas.';
        }

        $dibuat = (int) trim((string) shell_exec('grep -c "^CREATE TABLE" '.escapeshellarg($berkas)));
        if ($dibuat < $jumlahTabel) {
            return "Cadangan tidak lengkap — hanya {$dibuat} CREATE TABLE untuk {$jumlahTabel} tabel.";
        }

        return null;
    }

    private function kompres(string $berkas): string
    {
        $gz = $berkas.'.gz';
        $masuk = fopen($berkas, 'rb');
        $keluar = gzopen($gz, 'wb9');

        while (! feof($masuk)) {
            gzwrite($keluar, (string) fread($masuk, 1 << 20));
        }

        fclose($masuk);
        gzclose($keluar);
        unlink($berkas);

        return $gz;
    }
}

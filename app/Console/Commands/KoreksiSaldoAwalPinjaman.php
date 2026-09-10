<?php

namespace App\Console\Commands;

use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceLoan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Menyelaraskan `opening_balance_loans` sebuah batch dengan berkas
 * "Daftar Pinjaman" yang sudah dicocokkan ke neraca.
 *
 * Berkas daftar pinjaman BUKAN template import saldo awal: ia laporan dengan
 * kolom sendiri (Sisa Pinjaman / Sisa Jasa), tanpa kolektibilitas, tanpa kode
 * produk, dan tenornya dalam HARI sedangkan basis data menyimpan bulan. Karena
 * itu perintah ini hanya menyentuh kolom yang benar-benar ada di berkas dan
 * membiarkan sisanya apa adanya — kolom yang tidak punya sumber data tidak
 * boleh ditebak, sebab menimpanya dengan nilai default justru merusak data
 * yang selama ini benar.
 *
 * Bawaan perintah ini adalah uji-kering: tanpa --terapkan tidak ada satu baris
 * pun yang ditulis, dan laporannya tetap dihasilkan supaya selisihnya dapat
 * diperiksa lebih dulu.
 */
#[Signature('saldo-awal:koreksi-pinjaman
    {batch : ID batch saldo awal (lihat /admin/saldo-awal)}
    {berkas : Path berkas .xlsx daftar pinjaman yang sudah benar}
    {--terapkan : Tulis perubahan; tanpa ini perintah hanya melapor (uji-kering)}
    {--tenor=abaikan : Perlakuan kolom tenor — "abaikan" (bawaan) atau "hari" untuk menyalin jangka waktu hari apa adanya}
    {--produk= : Kode produk pinjaman untuk baris yang belum ada di basis data}
    {--hapus-selisih : Hapus baris basis data yang tidak ada di berkas}
    {--paksa : Izinkan menyentuh batch yang sudah dikunci (berbahaya, lihat catatan)}
    {--keluaran= : Direktori laporan, default storage/app/laporan}')]
#[Description('Menyelaraskan saldo awal pinjaman sebuah batch dengan berkas daftar pinjaman yang benar')]
class KoreksiSaldoAwalPinjaman extends Command
{
    /** Nama kolom berkas (sudah dinormalkan) -> nama internal. */
    private const KOLOM = [
        'tanggal pinjaman' => 'tanggal',
        'nomor anggota' => 'no_anggota',
        'nama anggota' => 'nama',
        'jumlah pinjaman' => 'pokok',
        'jasa pinjaman' => 'jasa',
        'sisa pinjaman' => 'sisa_pokok',
        'sisa jasa' => 'sisa_jasa',
        'jatuh tempo' => 'jatuh_tempo',
        'jangka waktu pinjaman dalam hari' => 'hari',
    ];

    public function handle(): int
    {
        $batch = OpeningBalanceBatch::query()->find((int) $this->argument('batch'));

        if (! $batch) {
            $this->error('Batch saldo awal #'.$this->argument('batch').' tidak ditemukan.');

            return self::FAILURE;
        }

        $berkas = (string) $this->argument('berkas');
        if (! is_file($berkas)) {
            $this->error("Berkas {$berkas} tidak ditemukan.");

            return self::FAILURE;
        }

        if (! in_array($this->option('tenor'), ['abaikan', 'hari'], true)) {
            $this->error('--tenor hanya menerima "abaikan" atau "hari".');

            return self::FAILURE;
        }

        [$barisBerkas, $galatBerkas] = $this->bacaBerkas($berkas);

        if ($galatBerkas !== []) {
            $this->error('Berkas tidak dapat dibaca:');
            foreach ($galatBerkas as $g) {
                $this->line('  - '.$g);
            }

            return self::FAILURE;
        }

        $this->info(sprintf('Berkas terbaca: %d baris pinjaman.', count($barisBerkas)));
        $this->baris('Batch', sprintf('#%d, cutoff %s, status %s', $batch->id, $batch->cutoff_date->toDateString(), $batch->status));

        if (! $batch->isDraft() && ! $this->option('paksa')) {
            $this->newLine();
            $this->error('Batch ini sudah DIKUNCI.');
            $this->line('Mengunci batch sudah memposting jurnal pembukaan dan membuat baris');
            $this->line('loans/loan_schedules yang sesungguhnya. Mengubah opening_balance_loans');
            $this->line('sekarang TIDAK akan memperbaiki keduanya — angka pinjaman dan neraca');
            $this->line('justru jadi berbeda. Koreksi setelah kunci ditempuh lewat jurnal');
            $this->line('penyesuaian (RUNBOOK §5.6).');
            $this->newLine();
            $this->line('Jalankan ulang dengan --paksa hanya bila Anda sudah memutuskan');
            $this->line('menangani jurnal dan tabel loans secara terpisah.');

            return self::FAILURE;
        }

        $rencana = $this->susunRencana($batch, $barisBerkas);

        $this->tampilkanRingkasan($rencana);

        $dirLaporan = $this->tulisLaporan($batch, $rencana);
        $this->newLine();
        $this->info("Laporan ditulis ke {$dirLaporan}");

        if ($rencana['galat'] !== []) {
            $this->newLine();
            $this->error(sprintf('%d baris berkas bermasalah — tidak ada perubahan yang diterapkan.', count($rencana['galat'])));
            $this->line('Rinciannya ada di laporan galat.csv.');

            return self::FAILURE;
        }

        if (! $this->option('terapkan')) {
            $this->newLine();
            $this->warn('UJI-KERING — tidak ada data yang diubah. Tambahkan --terapkan untuk menulis.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Menerapkan perubahan ...');

        DB::transaction(function () use ($rencana): void {
            foreach ($rencana['ubah'] as $item) {
                OpeningBalanceLoan::query()->whereKey($item['id'])->update($item['nilai_baru']);
            }

            foreach ($rencana['tambah'] as $item) {
                OpeningBalanceLoan::query()->create($item['nilai_baru']);
            }

            if ($this->option('hapus-selisih')) {
                foreach ($rencana['hapus'] as $item) {
                    OpeningBalanceLoan::query()->whereKey($item['id'])->delete();
                }
            }
        });

        $this->info(sprintf(
            'Selesai: %d diubah, %d ditambah, %d dihapus.',
            count($rencana['ubah']),
            count($rencana['tambah']),
            $this->option('hapus-selisih') ? count($rencana['hapus']) : 0,
        ));

        $sesudah = OpeningBalanceLoan::query()
            ->where('opening_balance_batch_id', $batch->id)
            ->selectRaw('COUNT(*) n, COALESCE(SUM(outstanding_principal),0) p, COALESCE(SUM(outstanding_interest),0) j')
            ->first();

        $this->newLine();
        $this->table(['Sesudah koreksi', 'Nilai'], [
            ['Jumlah baris', (string) $sesudah->n],
            ['Total sisa pokok', number_format((float) $sesudah->p, 2, ',', '.')],
            ['Total sisa jasa', number_format((float) $sesudah->j, 2, ',', '.')],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function bacaBerkas(string $path): array
    {
        $lembar = IOFactory::load($path)->getSheet(0);
        $baris = $lembar->toArray(null, true, false, false);

        $petaKolom = [];
        $barisJudul = null;

        foreach ($baris as $i => $isi) {
            $cocok = [];
            foreach ($isi as $kolom => $nilai) {
                $kunci = $this->normalkan((string) $nilai);
                if (isset(self::KOLOM[$kunci])) {
                    $cocok[self::KOLOM[$kunci]] = $kolom;
                }
            }

            if (count($cocok) >= 6) {
                $petaKolom = $cocok;
                $barisJudul = $i;
                break;
            }
        }

        if ($barisJudul === null) {
            return [[], ['Baris judul tidak ditemukan. Kolom yang diharapkan: '.implode(', ', array_keys(self::KOLOM))]];
        }

        $wajib = ['no_anggota', 'pokok', 'sisa_pokok', 'sisa_jasa', 'tanggal', 'jatuh_tempo'];
        $hilang = array_diff($wajib, array_keys($petaKolom));
        if ($hilang !== []) {
            return [[], ['Kolom wajib tidak ada di berkas: '.implode(', ', $hilang)]];
        }

        $hasil = [];
        foreach ($baris as $i => $isi) {
            if ($i <= $barisJudul) {
                continue;
            }

            $ambil = fn (string $nama) => isset($petaKolom[$nama]) ? ($isi[$petaKolom[$nama]] ?? null) : null;

            if ($this->normalkan((string) $ambil('no_anggota')) === '') {
                continue;
            }

            $hasil[] = [
                'baris_berkas' => $i + 1,
                'no_anggota' => trim((string) $ambil('no_anggota')),
                'nama' => trim((string) $ambil('nama')),
                'tanggal' => $this->tanggal($ambil('tanggal')),
                'jatuh_tempo' => $this->tanggal($ambil('jatuh_tempo')),
                'pokok' => $this->angka($ambil('pokok')),
                'jasa' => $this->angka($ambil('jasa')),
                'sisa_pokok' => $this->angka($ambil('sisa_pokok')),
                'sisa_jasa' => $this->angka($ambil('sisa_jasa')),
                'hari' => $ambil('hari') === null || $ambil('hari') === '' ? null : (int) $ambil('hari'),
            ];
        }

        return [$hasil, []];
    }

    /**
     * @param  list<array<string, mixed>>  $barisBerkas
     * @return array<string, mixed>
     */
    private function susunRencana(OpeningBalanceBatch $batch, array $barisBerkas): array
    {
        $adaSekarang = OpeningBalanceLoan::query()
            ->with('member')
            ->where('opening_balance_batch_id', $batch->id)
            ->get();

        // Satu anggota bisa punya lebih dari satu baris pinjaman; kunci
        // pencocokan karena itu nomor anggota + tanggal akad, bukan nomor
        // anggota saja. Tanpa tanggal, dua pinjaman milik orang yang sama akan
        // saling menimpa dan salah satunya hilang tanpa jejak.
        $indeksDb = [];
        foreach ($adaSekarang as $row) {
            $indeksDb[$this->kunci($row->member->member_number, $row->disbursement_date->toDateString())][] = $row;
        }

        $produkBaku = $this->option('produk')
            ? LoanProduct::query()->where('code', $this->option('produk'))->first()
            : null;

        if ($this->option('produk') && ! $produkBaku) {
            $this->warn('Kode produk "'.$this->option('produk').'" tidak ditemukan — baris baru akan dilaporkan sebagai galat.');
        }

        $ubah = $tambah = $tetap = $galat = [];
        $terpakai = [];

        foreach ($barisBerkas as $b) {
            $anggota = $this->cariAnggota((string) $b['no_anggota']);

            if (! $anggota) {
                $galat[] = $b + ['sebab' => "nomor anggota \"{$b['no_anggota']}\" tidak ada di Master Anggota"];

                continue;
            }

            if ($b['tanggal'] === null || $b['jatuh_tempo'] === null) {
                $galat[] = $b + ['sebab' => 'tanggal pinjaman atau jatuh tempo tidak terbaca sebagai tanggal'];

                continue;
            }

            if ($b['sisa_pokok'] === null || $b['sisa_jasa'] === null || $b['pokok'] === null) {
                $galat[] = $b + ['sebab' => 'kolom nominal tidak terbaca sebagai angka'];

                continue;
            }

            // Saldo AWAL adalah potret per tanggal cutoff: pinjaman yang akadnya
            // baru terjadi sesudah tanggal itu mustahil ada di dalamnya. Yang
            // tertangkap di sini biasanya tanggal tertukar hari/bulan — dan
            // kolom "jangka waktu" ikut jadi negatif karena ia selisih tanggal.
            // Ditolak, bukan diperbaiki sendiri: hanya koperasi yang tahu
            // tanggal akad yang sebenarnya.
            if ($b['tanggal'] > $batch->cutoff_date->toDateString()) {
                $galat[] = $b + ['sebab' => sprintf(
                    'tanggal pinjaman %s melewati cutoff batch %s — periksa kembali tanggalnya (jangka waktu terbaca %s hari)',
                    $b['tanggal'],
                    $batch->cutoff_date->toDateString(),
                    $b['hari'] ?? '?',
                )];

                continue;
            }

            $kunci = $this->kunci($anggota->member_number, $b['tanggal']);
            $kandidat = $indeksDb[$kunci] ?? [];
            $cocok = null;

            foreach ($kandidat as $row) {
                if (! in_array($row->id, $terpakai, true)) {
                    $cocok = $row;
                    $terpakai[] = $row->id;
                    break;
                }
            }

            $nilai = [
                'disbursement_date' => $b['tanggal'],
                'original_principal' => $b['pokok'],
                'outstanding_principal' => $b['sisa_pokok'],
                'outstanding_interest' => $b['sisa_jasa'],
                'next_due_date' => $b['jatuh_tempo'],
            ];

            if ($this->option('tenor') === 'hari' && $b['hari'] !== null && $b['hari'] > 0) {
                $nilai['tenor_months'] = $b['hari'];
            }

            if ($cocok) {
                $beda = $this->beda($cocok, $nilai);

                if ($beda === []) {
                    $tetap[] = ['id' => $cocok->id, 'no_anggota' => $anggota->member_number, 'nama' => $anggota->name];

                    continue;
                }

                $ubah[] = [
                    'id' => $cocok->id,
                    'no_anggota' => $anggota->member_number,
                    'nama' => $anggota->name,
                    'baris_berkas' => $b['baris_berkas'],
                    'beda' => $beda,
                    'nilai_baru' => $nilai,
                ];

                continue;
            }

            if (! $produkBaku) {
                $galat[] = $b + ['sebab' => 'baris ini belum ada di basis data; sebutkan --produk=KODE agar dapat ditambahkan'];

                continue;
            }

            $tambah[] = [
                'no_anggota' => $anggota->member_number,
                'nama' => $anggota->name,
                'baris_berkas' => $b['baris_berkas'],
                'nilai_baru' => $nilai + [
                    'opening_balance_batch_id' => $batch->id,
                    'member_id' => $anggota->id,
                    'loan_product_id' => $produkBaku->id,
                    'tenor_months' => $nilai['tenor_months'] ?? max(1, (int) ceil((float) ($b['hari'] ?? 30) / 30)),
                    'remaining_tenor_months' => $nilai['tenor_months'] ?? max(1, (int) ceil((float) ($b['hari'] ?? 30) / 30)),
                    'next_installment_number' => 1,
                    'collectibility' => 'lancar',
                ],
            ];
        }

        $hapus = [];
        foreach ($adaSekarang as $row) {
            if (! in_array($row->id, $terpakai, true)) {
                $hapus[] = [
                    'id' => $row->id,
                    'no_anggota' => $row->member->member_number,
                    'nama' => $row->member->name,
                    'tanggal' => $row->disbursement_date->toDateString(),
                    'sisa_pokok' => (float) $row->outstanding_principal,
                    'sisa_jasa' => (float) $row->outstanding_interest,
                ];
            }
        }

        return [
            'ubah' => $ubah,
            'tambah' => $tambah,
            'hapus' => $hapus,
            'tetap' => $tetap,
            'galat' => $galat,
            'angsuran' => $this->laporanAngsuran($batch, $ubah, $hapus),
            'total_berkas' => [
                'sisa_pokok' => array_sum(array_map(fn ($b) => (float) ($b['sisa_pokok'] ?? 0), $barisBerkas)),
                'sisa_jasa' => array_sum(array_map(fn ($b) => (float) ($b['sisa_jasa'] ?? 0), $barisBerkas)),
                'baris' => count($barisBerkas),
            ],
            'total_db' => [
                'sisa_pokok' => (float) $adaSekarang->sum('outstanding_principal'),
                'sisa_jasa' => (float) $adaSekarang->sum('outstanding_interest'),
                'baris' => $adaSekarang->count(),
            ],
        ];
    }

    /**
     * Angsuran yang sudah terlanjur dibayar atas pinjaman hasil batch ini.
     *
     * Pinjaman hasil migrasi dikenali dari loan_number-nya — lihat
     * OpeningBalanceLockService::materializeLoans(), yang memakai
     * external_loan_number bila ada dan "MIGRASI-<id baris saldo awal>" bila
     * tidak. Selama batch masih draft belum ada pinjaman yang dibuat, jadi
     * daftar ini wajar kosong.
     *
     * @param  list<array<string, mixed>>  $ubah
     * @param  list<array<string, mixed>>  $hapus
     * @return list<array<string, mixed>>
     */
    private function laporanAngsuran(OpeningBalanceBatch $batch, array $ubah, array $hapus): array
    {
        $terdampak = [];
        foreach ($ubah as $item) {
            $terdampak[$item['id']] = $item['nilai_baru']['outstanding_principal'] + $item['nilai_baru']['outstanding_interest'];
        }
        foreach ($hapus as $item) {
            $terdampak[$item['id']] = 0.0;
        }

        if ($terdampak === []) {
            return [];
        }

        $barisSaldo = OpeningBalanceLoan::query()
            ->with('member')
            ->whereKey(array_keys($terdampak))
            ->get();

        $nomor = [];
        foreach ($barisSaldo as $row) {
            $nomor[$row->external_loan_number ?? ('MIGRASI-'.$row->id)] = $row;
        }

        if ($nomor === []) {
            return [];
        }

        $angsuran = DB::table('loan_repayments as r')
            ->join('loans as l', 'l.id', '=', 'r.loan_id')
            ->join('members as m', 'm.id', '=', 'l.member_id')
            ->whereIn('l.loan_number', array_keys($nomor))
            ->orderBy('m.member_number')
            ->orderBy('r.created_at')
            ->get([
                'r.id as angsuran_id', 'r.loan_id', 'r.amount', 'r.principal_portion',
                'r.interest_portion', 'r.balance_after', 'r.journal_entry_id',
                'r.cancelled_at', 'r.created_at', 'r.description',
                'l.loan_number', 'l.principal_amount',
                'm.member_number', 'm.name as nama_anggota',
            ]);

        $terbayarPerPinjaman = [];
        foreach ($angsuran as $a) {
            if ($a->cancelled_at === null) {
                $terbayarPerPinjaman[$a->loan_number] = ($terbayarPerPinjaman[$a->loan_number] ?? 0) + (float) $a->amount;
            }
        }

        $hasil = [];
        foreach ($angsuran as $a) {
            $row = $nomor[$a->loan_number];
            $saldoBaru = $terdampak[$row->id];
            $saldoLama = (float) $row->outstanding_principal + (float) $row->outstanding_interest;
            $terbayar = $terbayarPerPinjaman[$a->loan_number] ?? 0.0;

            $hasil[] = [
                'angsuran_id' => $a->angsuran_id,
                'no_anggota' => $a->member_number,
                'nama' => $a->nama_anggota,
                'no_pinjaman' => $a->loan_number,
                'tanggal_angsuran' => (string) $a->created_at,
                'nominal' => (float) $a->amount,
                'porsi_pokok' => (float) $a->principal_portion,
                'porsi_jasa' => (float) $a->interest_portion,
                'jurnal_id' => $a->journal_entry_id,
                'sudah_dibatalkan' => $a->cancelled_at !== null ? 'ya' : 'tidak',
                'saldo_awal_lama' => $saldoLama,
                'saldo_awal_baru' => $saldoBaru,
                'total_terbayar' => $terbayar,
                'tindakan' => $this->tindakanAngsuran($a->cancelled_at !== null, $saldoBaru, $terbayar),
            ];
        }

        return $hasil;
    }

    private function tindakanAngsuran(bool $sudahBatal, float $saldoBaru, float $terbayar): string
    {
        if ($sudahBatal) {
            return 'tidak perlu — sudah dibatalkan';
        }

        if ($saldoBaru <= 0.0) {
            return 'BATALKAN — saldo awal pinjaman ini dihapus/nol setelah koreksi';
        }

        if ($terbayar > $saldoBaru + 0.005) {
            return 'BATALKAN — total angsuran melebihi saldo awal hasil koreksi';
        }

        return 'EDIT — alokasi pokok/jasa perlu dihitung ulang atas saldo awal baru';
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array<string, array{lama: string, baru: string}>
     */
    private function beda(OpeningBalanceLoan $row, array $nilai): array
    {
        $beda = [];

        foreach ($nilai as $kolom => $baru) {
            $lama = $row->{$kolom};

            if (in_array($kolom, ['disbursement_date', 'next_due_date'], true)) {
                $lamaTeks = $lama?->toDateString() ?? '';
                $baruTeks = (string) $baru;
            } elseif ($kolom === 'tenor_months') {
                $lamaTeks = (string) (int) $lama;
                $baruTeks = (string) (int) $baru;
            } else {
                $lamaTeks = number_format((float) $lama, 2, '.', '');
                $baruTeks = number_format((float) $baru, 2, '.', '');
            }

            if ($lamaTeks !== $baruTeks) {
                $beda[$kolom] = ['lama' => $lamaTeks, 'baru' => $baruTeks];
            }
        }

        return $beda;
    }

    /**
     * Nomor anggota di berkas kerap terbaca sebagai angka sehingga nol di
     * depannya hilang. Pencocokan karena itu dicoba apa adanya lebih dulu,
     * baru kemudian tanpa nol di depan pada kedua sisi.
     */
    private function cariAnggota(string $nomor): ?Member
    {
        $nomor = trim($nomor);

        if ($nomor === '') {
            return null;
        }

        static $cache = [];

        if (array_key_exists($nomor, $cache)) {
            return $cache[$nomor];
        }

        $anggota = Member::query()->where('member_number', $nomor)->first()
            ?? Member::query()->whereRaw("TRIM(LEADING '0' FROM member_number) = ?", [ltrim($nomor, '0')])->first();

        return $cache[$nomor] = $anggota;
    }

    private function kunci(string $noAnggota, string $tanggal): string
    {
        return ltrim(trim($noAnggota), '0').'|'.$tanggal;
    }

    private function normalkan(string $teks): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($teks)));
    }

    private function tanggal(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if (is_numeric($nilai)) {
            return ExcelDate::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
        }

        try {
            return (new \DateTimeImmutable((string) $nilai))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function angka(mixed $nilai): ?float
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        if (is_numeric($nilai)) {
            return (float) $nilai;
        }

        // "1.234.567,89" (format Indonesia) maupun "1,234,567.89" harus lolos.
        $bersih = preg_replace('/[^0-9,.\-]/', '', (string) $nilai);

        if (str_contains($bersih, ',') && str_contains($bersih, '.')) {
            $bersih = strrpos($bersih, ',') > strrpos($bersih, '.')
                ? str_replace(['.', ','], ['', '.'], $bersih)
                : str_replace(',', '', $bersih);
        } elseif (str_contains($bersih, ',')) {
            $bersih = str_replace(',', '.', $bersih);
        }

        return is_numeric($bersih) ? (float) $bersih : null;
    }

    /**
     * @param  array<string, mixed>  $rencana
     */
    private function tampilkanRingkasan(array $rencana): void
    {
        $this->newLine();
        $this->table(['Perbandingan', 'Berkas', 'Basis data'], [
            ['Jumlah baris', (string) $rencana['total_berkas']['baris'], (string) $rencana['total_db']['baris']],
            ['Total sisa pokok', $this->rp($rencana['total_berkas']['sisa_pokok']), $this->rp($rencana['total_db']['sisa_pokok'])],
            ['Total sisa jasa', $this->rp($rencana['total_berkas']['sisa_jasa']), $this->rp($rencana['total_db']['sisa_jasa'])],
        ]);

        $this->newLine();
        $this->table(['Rencana', 'Jumlah baris'], [
            ['Diubah', (string) count($rencana['ubah'])],
            ['Ditambah', (string) count($rencana['tambah'])],
            ['Dihapus'.($this->option('hapus-selisih') ? '' : ' (dilaporkan saja)'), (string) count($rencana['hapus'])],
            ['Sudah sama', (string) count($rencana['tetap'])],
            ['Bermasalah', (string) count($rencana['galat'])],
        ]);

        if ($rencana['angsuran'] !== []) {
            $perlu = array_filter($rencana['angsuran'], fn ($a) => ! str_starts_with($a['tindakan'], 'tidak perlu'));
            $this->newLine();
            $this->warn(sprintf(
                'Ada %d transaksi angsuran atas pinjaman yang saldo awalnya berubah (%d perlu ditindaklanjuti).',
                count($rencana['angsuran']),
                count($perlu),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $rencana
     */
    private function tulisLaporan(OpeningBalanceBatch $batch, array $rencana): string
    {
        $dir = ($this->option('keluaran') ?: storage_path('app/laporan'))
            .'/koreksi-saldo-awal-batch'.$batch->id.'-'.now()->format('Ymd-His');

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Gagal membuat direktori laporan {$dir}");
        }

        $this->csv("{$dir}/perubahan.csv",
            ['no_anggota', 'nama', 'baris_berkas', 'kolom', 'nilai_lama', 'nilai_baru'],
            $this->ratakanPerubahan($rencana['ubah']),
        );

        $this->csv("{$dir}/ditambah.csv",
            ['no_anggota', 'nama', 'baris_berkas', 'tanggal', 'sisa_pokok', 'sisa_jasa'],
            array_map(fn ($i) => [
                $i['no_anggota'], $i['nama'], $i['baris_berkas'],
                $i['nilai_baru']['disbursement_date'],
                $i['nilai_baru']['outstanding_principal'],
                $i['nilai_baru']['outstanding_interest'],
            ], $rencana['tambah']),
        );

        $this->csv("{$dir}/tidak-ada-di-berkas.csv",
            ['id', 'no_anggota', 'nama', 'tanggal', 'sisa_pokok', 'sisa_jasa'],
            array_map(fn ($i) => array_values($i), $rencana['hapus']),
        );

        $this->csv("{$dir}/galat.csv",
            ['baris_berkas', 'no_anggota', 'nama', 'sisa_pokok', 'sisa_jasa', 'sebab'],
            array_map(fn ($i) => [
                $i['baris_berkas'] ?? '', $i['no_anggota'] ?? '', $i['nama'] ?? '',
                $i['sisa_pokok'] ?? '', $i['sisa_jasa'] ?? '', $i['sebab'],
            ], $rencana['galat']),
        );

        $this->csv("{$dir}/tindak-lanjut-angsuran.csv",
            ['angsuran_id', 'no_anggota', 'nama', 'no_pinjaman', 'tanggal_angsuran', 'nominal',
                'porsi_pokok', 'porsi_jasa', 'jurnal_id', 'sudah_dibatalkan',
                'saldo_awal_lama', 'saldo_awal_baru', 'total_terbayar', 'tindakan'],
            array_map(fn ($i) => array_values($i), $rencana['angsuran']),
        );

        return $dir;
    }

    /**
     * @param  list<array<string, mixed>>  $ubah
     * @return list<list<string>>
     */
    private function ratakanPerubahan(array $ubah): array
    {
        $baris = [];

        foreach ($ubah as $item) {
            foreach ($item['beda'] as $kolom => $nilai) {
                $baris[] = [
                    (string) $item['no_anggota'],
                    (string) $item['nama'],
                    (string) $item['baris_berkas'],
                    $kolom,
                    $nilai['lama'],
                    $nilai['baru'],
                ];
            }
        }

        return $baris;
    }

    /**
     * @param  list<string>  $judul
     * @param  list<array<int, mixed>>  $baris
     */
    private function csv(string $path, array $judul, array $baris): void
    {
        $fh = fopen($path, 'wb');
        fputcsv($fh, $judul);

        foreach ($baris as $b) {
            fputcsv($fh, $b);
        }

        fclose($fh);
    }

    private function rp(float $nilai): string
    {
        return number_format($nilai, 2, ',', '.');
    }

    private function baris(string $label, string $nilai): void
    {
        $this->line(sprintf('<info>%s</info>: %s', $label, $nilai));
    }
}

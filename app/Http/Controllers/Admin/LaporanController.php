<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\BusinessUnitTransaction;
use App\Models\ChartOfAccount;
use App\Models\CooperativeEvent;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDisposal;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\OpeningBalanceBatch;
use App\Models\OpeningBalanceCoa;
use App\Models\OpeningBalanceLoan;
use App\Models\OpeningBalanceSaving;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseTransaction;
use App\Models\RetributionTransaction;
use App\Models\RetributionType;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\StockAdjustment;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\TellerCashTransaction;
use App\Models\User;
use App\Services\Inventory\InventoryReportService;
use App\Services\Reporting\LaporanRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidated "Laporan" hub — one page per form/fitur that has data, all
 * displayed as a searchable/filterable datatable with PDF+Excel export.
 * fetch() is the single dispatch point; each private per-module method
 * returns rows already formatted for display (money/date/status as ready-
 * to-print strings) so the same array feeds the web table, the PDF view,
 * and the Excel export identically — no per-surface reformatting.
 *
 * Row-level branch scoping is NOT done manually here for most modules: every
 * transactional model already applies BranchScope as a global scope keyed
 * off the authenticated user (see App\Models\Scopes\BranchScope), so a plain
 * ::query() is already restricted. CooperativeEvent is the one exception
 * (its own multi-branch/role scoping shape, no BranchScope trait), so it is
 * filtered manually here exactly like CalendarService does.
 */
class LaporanController extends Controller
{
    use GeneratesPrintPdf;

    /**
     * Cetakan menyaring baris yang saldonya sudah nol supaya tidak menghabiskan
     * kertas untuk rekening dan pinjaman mati. Layar dan Excel TIDAK disaring —
     * keduanya dipakai untuk menelusuri riwayat, dan menyembunyikan baris di
     * sana akan membuat data terlihat hilang.
     *
     * Karena itu jumlah baris PDF bisa lebih sedikit daripada di layar, dan
     * alasannya dicetak di kepala halaman lewat catatan ini — laporan keuangan
     * yang diam-diam memangkas baris jauh lebih berbahaya daripada yang tebal.
     */
    private const CATATAN_SARINGAN_CETAK = [
        'simpanan_rekening' => 'Hanya rekening bersaldo di atas nol. Rekening bersaldo nol tidak dicetak — lihat Export Excel untuk daftar lengkap.',
        'pinjaman' => 'Hanya pinjaman yang belum lunas. Pinjaman berstatus Lunas tidak dicetak — lihat Export Excel untuk daftar lengkap.',
    ];

    public function __construct(private readonly InventoryReportService $inventoryReportService) {}

    public function index(): View
    {
        $this->authorize('laporan.read');

        return view('admin.laporan.index', [
            'groups' => LaporanRegistry::groups(),
        ]);
    }

    /**
     * Layar dibatasi jumlah barisnya, ekspor tidak.
     *
     * Riwayat Pembayaran hasil migrasi berisi 21 ribu baris. Merendernya jadi
     * satu tabel HTML menembus memory_limit saat Blade menyusun keluarannya —
     * bukan saat query berjalan — dan halamannya mati dengan 500 tanpa
     * keterangan. Sekalipun memorinya dinaikkan sampai muat, tabel sebesar itu
     * melumpuhkan saringan datatable yang menyisir seluruh baris tiap ketikan.
     *
     * Karena itu layar memotong di ambang yang wajar dan mengatakannya
     * terang-terangan, sementara Export PDF/Excel tetap memakai seluruh baris
     * yang lolos saringan. Memotong diam-diam jauh lebih berbahaya: pengguna
     * akan mengira laporannya memang sesingkat itu.
     */
    public function show(string $module): View
    {
        $this->authorize('laporan.read');
        abort_unless(LaporanRegistry::exists($module), 404);

        ini_set('memory_limit', '512M');

        $rows = $this->fetch($module);
        $maxRows = (int) config('koperasi.laporan_maks_baris_layar', 5000);
        $jumlahPenuh = $rows->count();

        return view('admin.laporan.show', [
            'module' => $module,
            'label' => LaporanRegistry::labelFor($module),
            'columns' => LaporanRegistry::columnsFor($module),
            'filterable' => LaporanRegistry::filterableFor($module),
            'dateColumn' => LaporanRegistry::dateColumnFor($module),
            'rows' => $jumlahPenuh > $maxRows ? $rows->take($maxRows)->values() : $rows,
            'jumlahPenuh' => $jumlahPenuh,
            'dipotong' => $jumlahPenuh > $maxRows,
            'maksBarisLayar' => $maxRows,
        ]);
    }

    /**
     * Terapkan saringan yang sedang aktif di layar ke baris hasil fetch().
     *
     * Aturannya sengaja meniru persis datatable di layouts/app.blade.php —
     * pencarian bebas mencocokkan seluruh isi baris tanpa memandang huruf
     * besar/kecil, filter kolom mencocokkan isi sel secara utuh, dan rentang
     * tanggal membandingkan kolom tanggal setelah diubah ke yyyy-mm-dd. Baris
     * yang kolom tanggalnya tidak terbaca sengaja dibiarkan lolos, sama seperti
     * di layar; itulah yang menjaga baris sub total tetap ikut saat pengguna
     * hanya menyaring periode.
     *
     * Kalau salah satu aturan di sini menyimpang dari yang di layar, cetakannya
     * akan berbeda dari yang dilihat pengguna — dan justru itu yang hendak
     * dihindari.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function saringSepertiDiLayar(Collection $rows, Request $request, string $module): Collection
    {
        $cari = mb_strtolower(trim((string) $request->query('cari', '')));
        $dari = trim((string) $request->query('dari', ''));
        $sampai = trim((string) $request->query('sampai', ''));
        $kolomTanggal = LaporanRegistry::dateColumnFor($module);

        $filterKolom = [];
        foreach (LaporanRegistry::filterableFor($module) as $kolom) {
            $nilai = trim((string) $request->query('f_'.$kolom, ''));

            if ($nilai !== '') {
                $filterKolom[$kolom] = $nilai;
            }
        }

        if ($cari === '' && $filterKolom === [] && $dari === '' && $sampai === '') {
            return $rows;
        }

        return $rows->filter(function (array $row) use ($cari, $filterKolom, $dari, $sampai, $kolomTanggal) {
            // Kunci berawalan garis bawah adalah penanda gaya baris, bukan isi
            // laporan — ikut dicocokkan, pencarian "ringkasan" akan menyapu
            // seluruh baris sub total.
            $isiBaris = array_filter(
                $row,
                fn (string $kunci) => ! str_starts_with($kunci, '_'),
                ARRAY_FILTER_USE_KEY,
            );

            if ($cari !== '' && ! str_contains(mb_strtolower(implode(' ', array_map('strval', $isiBaris))), $cari)) {
                return false;
            }

            foreach ($filterKolom as $kolom => $nilai) {
                if (trim((string) ($row[$kolom] ?? '')) !== $nilai) {
                    return false;
                }
            }

            if ($kolomTanggal !== null && ($dari !== '' || $sampai !== '')) {
                $tanggal = $this->tanggalBarisKeIso((string) ($row[$kolomTanggal] ?? ''));

                if ($tanggal !== null) {
                    if ($dari !== '' && $tanggal < $dari) {
                        return false;
                    }
                    if ($sampai !== '' && $tanggal > $sampai) {
                        return false;
                    }
                }
            }

            return true;
        })->values();
    }

    /** "d-m-Y ..." menjadi "Y-m-d" supaya bisa dibandingkan leksikal. */
    private function tanggalBarisKeIso(string $teks): ?string
    {
        return preg_match('/^(\d{2})-(\d{2})-(\d{4})/', trim($teks), $m) === 1
            ? $m[3].'-'.$m[2].'-'.$m[1]
            : null;
    }

    /**
     * Keterangan saringan yang ikut tercetak di kepala halaman.
     *
     * Cetakan hasil penyaringan tidak boleh bisa dikira laporan lengkap —
     * apalagi setelah dilepas dari layar tempat filternya terlihat.
     */
    private function catatanSaringan(Request $request, string $module): ?string
    {
        $bagian = [];

        if (($cari = trim((string) $request->query('cari', ''))) !== '') {
            $bagian[] = 'pencarian "'.$cari.'"';
        }

        foreach (LaporanRegistry::filterableFor($module) as $kolom) {
            if (($nilai = trim((string) $request->query('f_'.$kolom, ''))) !== '') {
                $label = LaporanRegistry::columnsFor($module)[$kolom] ?? $kolom;
                $bagian[] = $label.' = '.$nilai;
            }
        }

        $dari = trim((string) $request->query('dari', ''));
        $sampai = trim((string) $request->query('sampai', ''));

        if ($dari !== '' || $sampai !== '') {
            $bagian[] = 'periode '.($dari !== '' ? $dari : '…').' s/d '.($sampai !== '' ? $sampai : '…');
        }

        return $bagian === [] ? null : 'Disaring: '.implode(' · ', $bagian).'. Baris di luar saringan tidak dicetak.';
    }

    /**
     * Hub laporan memakai satu view cetak untuk 29 modul, jadi ukuran datanya
     * sangat bervariasi — Jenis Anggota belasan baris, Rekening Simpanan ribuan
     * setelah migrasi. DomPDF menyimpan pohon style tiap sel di memori dan
     * kehabisan batas PHP jauh sebelum halaman pertama selesai, muncul sebagai
     * 500 tanpa keterangan (lihat MemberController::printList() untuk kejadian
     * yang sama di Daftar Anggota).
     *
     * Batas baris ditegakkan SETELAH penyaringan dan diarahkan ke Export Excel,
     * yang menulis berkas secara bertahap dan tidak punya langit-langit serupa.
     * Urutan itu penting: laporan besar yang disaring jadi seratus baris tetap
     * boleh dicetak, karena yang membebani DomPDF adalah baris yang benar-benar
     * dirender, bukan baris di database.
     *
     * Angkanya diukur di produksi, bukan ditebak: Rekening Simpanan 1.224 baris
     * memuncak di 594 MB dan 25 detik — 0,458 MB dan 0,02 detik per baris. Batas
     * 1.400 baris berarti ~675 MB dan ~29 detik, masih di bawah kedua pagar di
     * bawah ini; di atas ~1.600 baris memory_limit-nya jebol.
     */
    public function exportPdf(Request $request, string $module): Response
    {
        $this->authorize('laporan.read');
        abort_unless(LaporanRegistry::exists($module), 404);

        ini_set('memory_limit', '768M');
        set_time_limit(300);

        $rows = $this->saringSepertiDiLayar($this->fetch($module, hanyaBersaldo: true), $request, $module);
        $maxRows = (int) config('koperasi.cetak_laporan_maks_baris', 1400);

        if ($rows->count() > $maxRows) {
            return redirect()
                ->route('admin.laporan.show', $module)
                ->with('error', 'Laporan ini berisi '.number_format($rows->count(), 0, ',', '.')
                    .' baris — terlalu besar untuk PDF (batas '.number_format($maxRows, 0, ',', '.')
                    .' baris). Pakai Export Excel; isinya sama persis.');
        }

        $columns = LaporanRegistry::columnsFor($module);

        $pdf = $this->renderPrintPdf('prints.laporan.generic', [
            'title' => LaporanRegistry::labelFor($module),
            'columns' => $columns,
            'rows' => $rows,
            'generatedAt' => now(),
            'catatan' => trim(implode(' ', array_filter([
                $this->catatanSaringan($request, $module),
                self::CATATAN_SARINGAN_CETAK[$module] ?? null,
            ]))) ?: null,
        ], $this->orientasiUntuk($columns));

        return $pdf->download(Str::slug($module).'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Tabel berkolom banyak diremas tidak terbaca di A4 portrait — kolom nama
     * anggota dan nama produk pecah jadi beberapa baris per sel. Cetakan lain
     * tetap mengikuti pilihan admin di Pengaturan Cetakan; hanya hub laporan
     * yang memaksa landscape, dan hanya bila kolomnya memang padat.
     *
     * @param  array<string, string>  $columns
     */
    private function orientasiUntuk(array $columns): ?string
    {
        $ambang = (int) config('koperasi.cetak_laporan_kolom_landscape', 7);

        return count($columns) >= $ambang ? 'landscape' : null;
    }

    /**
     * Excel ikut menghormati saringan layar, sama seperti PDF — dua tombol
     * bersebelahan yang menghasilkan isi berbeda dari satu tampilan yang sama
     * adalah jebakan.
     *
     * Bedanya hanya satu: Excel tidak menyaring baris bersaldo nol (lihat
     * CATATAN_SARINGAN_CETAK), karena itulah jalan keluar yang ditunjuk pesan
     * "terlalu besar untuk PDF" dan pemakaiannya untuk menelusuri riwayat.
     */
    public function exportExcel(Request $request, string $module): BinaryFileResponse
    {
        $this->authorize('laporan.read');
        abort_unless(LaporanRegistry::exists($module), 404);

        $columns = LaporanRegistry::columnsFor($module);
        $rows = $this->saringSepertiDiLayar($this->fetch($module), $request, $module)->map(
            fn (array $row) => array_map(fn (string $key) => $row[$key] ?? '-', array_keys($columns))
        );

        return Excel::download(
            new GenericListExport(array_values($columns), $rows),
            Str::slug($module).'-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * Cetak Saldo Awal Neraca dalam BENTUK SKONTRO (horizontal/T-form) —
     * kembar dengan FinancialReportController::printScontro() (Neraca
     * berjalan), tapi bersumber dari OpeningBalanceCoa (batch saldo awal
     * yang di-import saat migrasi dari sistem lama).
     *
     * Cakupan dikendalikan lewat query string:
     *   - Tanpa `branch_id` (atau `branch_id=`) → Konsolidasi seluruh
     *     cabang yang bisa diakses user.
     *   - `?branch_id=X` → hanya batch cabang X.
     * `cutoff_date` TIDAK bisa dipilih user karena saldo awal sebuah
     * batch nilainya sudah terkunci per tanggal yang ditetapkan waktu
     * lock (OpeningBalanceLockService); periode di cetakan diisi
     * otomatis dari cutoff_date batch yang termuat.
     *
     * Blade `prints.laporan.neraca-scontro` menerima `$balanceByCode`
     * (map kode akun → saldo positive-natural) dan membangun hierarki
     * sendiri via ChartOfAccount tree — sama persis dengan yang dipakai
     * Neraca berjalan, sehingga tata letak, kolom kontra (Penyisihan
     * Piutang, Akumulasi Penyusutan), dan sub-total per kelompok
     * konsisten antara "posisi keuangan hari ini" dan "posisi keuangan
     * pada saat migrasi".
     *
     * Baris LABA_RUGI (Pendapatan/Beban) TIDAK dikeluarkan sendiri
     * sebagai akun di neraca — mereka DIRINGKAS jadi "SHU Tahun Berjalan"
     * dan ditambahkan ke akun ekuitas SHU (config
     * koperasi.akun_shu_berjalan, default 3150). Tanpa langkah ini
     * scontro tidak akan seimbang persis sebesar SHU: sisi Aset sudah
     * memuat hasil dari Pendapatan (uang masuk) dan Beban (uang keluar),
     * sementara sisi Ekuitas kekurangan angka SHU-nya karena tutup buku
     * belum jalan — ini juga yang dilakukan saldoAwalCoa() untuk versi
     * flat dan FinancialReportEngine::neraca() untuk Neraca berjalan.
     */
    public function printSaldoAwalNeracaSkontro(Request $request): Response
    {
        $this->authorize('laporan.read');

        // Validasi hak akses cabang. `allowedBranchIds() === null` = super
        // admin lintas cabang; array = daftar id yang boleh; kalau user
        // request cabang di luar itu, tolak (jangan diam-diam pindah ke
        // cabang lain karena angka Neraca sensitif).
        $branchId = $request->integer('branch_id') ?: null;
        $allowed = $request->user()->allowedBranchIds();
        if ($branchId !== null && $allowed !== null && ! in_array($branchId, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        // BranchScope pada OpeningBalanceBatch (trait BelongsToBranch)
        // sudah mempersempit ke cabang yang boleh diakses; filter
        // `branch_id` di sini menambah spesifikasi kalau user pilih
        // satu cabang saja.
        $batches = OpeningBalanceBatch::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with('branch')
            ->get();

        $entries = OpeningBalanceCoa::query()
            ->whereIn('opening_balance_batch_id', $batches->pluck('id'))
            ->with('account')
            ->get();

        $shuAccountCode = (string) config('koperasi.akun_shu_berjalan', '3150');
        $balanceByCode = [];
        $shuFromLabaRugi = 0.0;

        foreach ($entries as $line) {
            $account = $line->account;
            if (! $account) {
                continue;
            }

            $amount = (float) $line->amount;

            if ($account->statement === 'LABA_RUGI') {
                // Kontribusi ke SHU Tahun Berjalan — formula identik
                // dengan saldoAwalCoa() versi flat: kredit → nambah,
                // debit → mengurangi.
                $shuFromLabaRugi += ($line->position === 'kredit' ? $amount : -$amount);
                continue;
            }

            if ($account->statement !== 'NERACA') {
                continue;
            }

            $isDebitNormal = $account->normal_balance === 'DEBIT';
            $delta = $isDebitNormal
                ? ($line->position === 'debit' ? $amount : -$amount)
                : ($line->position === 'kredit' ? $amount : -$amount);
            $balanceByCode[$account->code] = ($balanceByCode[$account->code] ?? 0.0) + $delta;
        }

        if (round($shuFromLabaRugi, 2) !== 0.0) {
            $balanceByCode[$shuAccountCode] = ($balanceByCode[$shuAccountCode] ?? 0.0) + $shuFromLabaRugi;
        }

        // Meta cabang. Kalau branch_id kosong tapi user cuma punya akses
        // 1 cabang, tetap tampilkan namanya, bukan "Konsolidasi" — supaya
        // pembaca tahu ini bukan angka konsolidasi lintas cabang.
        if ($branchId !== null) {
            $branchName = optional(Branch::query()->find($branchId))->name ?? 'Cabang #'.$branchId;
        } elseif ($batches->count() === 1) {
            $branchName = optional($batches->first()->branch)->name ?? 'Cabang tunggal';
        } else {
            $branchName = 'Konsolidasi Seluruh Cabang ('.$batches->count().' batch)';
        }

        // Meta periode = cutoff_date batch. Sebuah batch punya satu
        // cutoff, konsolidasi bisa multi-cutoff. Tampilkan tanggal unik
        // atau range kalau tidak sama.
        $cutoffs = $batches->pluck('cutoff_date')->filter()->map(fn ($d) => $d->toDateString())->unique()->sort()->values();
        if ($cutoffs->isEmpty()) {
            $periodeLabel = 'Belum ada batch saldo awal terdaftar';
        } else {
            $first = \Illuminate\Support\Carbon::parse($cutoffs->first())->translatedFormat('d M Y');
            $last = \Illuminate\Support\Carbon::parse($cutoffs->last())->translatedFormat('d M Y');
            $periodeLabel = $cutoffs->count() === 1
                ? 'Cutoff '.$first
                : 'Cutoff '.$first.' s/d '.$last;
        }

        $pdf = $this->renderPrintPdf('prints.laporan.neraca-scontro', [
            'title' => 'Saldo Awal — Neraca (Bentuk Skontro)',
            'balanceByCode' => $balanceByCode,
            'meta' => [
                'sub_title' => 'Posisi keuangan pada saat migrasi data dari sistem lama',
                'periode' => $periodeLabel,
                'cabang' => $branchName,
                'petugas' => optional($request->user())->name ?? '-',
                'tgl_cetak' => now(),
            ],
        ], orientationOverride: 'landscape');

        $slug = $branchId ? 'cab'.$branchId : 'konsolidasi';

        return $pdf->stream('saldo-awal-neraca-scontro-'.$slug.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @param  bool  $hanyaBersaldo  Buang baris bersaldo nol — lihat CATATAN_SARINGAN_CETAK.
     * @return Collection<int, array<string, mixed>>
     */
    private function fetch(string $module, bool $hanyaBersaldo = false): Collection
    {
        return match ($module) {
            'anggota' => $this->anggota(),
            'jenis_anggota' => $this->jenisAnggota(),
            'bagan_akun' => $this->baganAkun(),
            'simpanan_rekening' => $this->simpananRekening($hanyaBersaldo),
            'simpanan_transaksi' => $this->simpananTransaksi(),
            'pinjaman' => $this->pinjaman($hanyaBersaldo),
            'angsuran' => $this->angsuran(),
            'transaksi_pinjaman' => $this->transaksiPinjaman(),
            'pembayaran_angsuran' => $this->pembayaranAngsuran(),
            'pengajuan_persetujuan_pinjaman' => $this->pengajuanPersetujuanPinjaman(),
            'barang' => $this->barang(),
            'supplier' => $this->supplier(),
            'pembelian' => $this->pembelian(),
            'retur_pembelian' => $this->returPembelian(),
            'koreksi_persediaan' => $this->koreksiPersediaan(),
            'persediaan_kartu' => $this->persediaanKartu(),
            'saldo_persediaan' => $this->saldoPersediaan(),
            'kategori_aktiva_tetap' => $this->kategoriAktivaTetap(),
            'aktiva_tetap' => $this->aktivaTetap(),
            'pelepasan_aktiva_tetap' => $this->pelepasanAktivaTetap(),
            'jenis_retribusi' => $this->jenisRetribusi(),
            'retribusi_upf' => $this->retribusiUpf(),
            'unit_usaha' => $this->unitUsaha(),
            'transaksi_unit_usaha' => $this->transaksiUnitUsaha(),
            'transaksi_pos' => $this->transaksiPos(),
            'transaksi_teller' => $this->transaksiTeller(),
            'kalender_kegiatan' => $this->kalenderKegiatan(),
            'jurnal_umum' => $this->jurnalUmum(),
            'saldo_awal_neraca' => $this->saldoAwalCoa(neracaSaja: true),
            'saldo_awal_neraca_saldo' => $this->saldoAwalCoa(neracaSaja: false),
            'saldo_awal_pinjaman' => $this->saldoAwalPinjaman(),
            'saldo_awal_simpanan' => $this->saldoAwalSimpanan(),
            'migrasi_historis_pembayaran' => $this->migrasiHistorisPembayaran(),
            'migrasi_jadwal_pembayaran' => $this->migrasiJadwalPembayaran(),
            'pengguna' => $this->pengguna(),
            default => collect(),
        };
    }

    /**
     * Penanda gaya baris, dibaca layar dan cetakan untuk memberi latar pembeda.
     *
     * Kuncinya diawali garis bawah supaya tidak pernah tertukar dengan kolom
     * laporan: view hanya merender kunci yang terdaftar di LaporanRegistry,
     * Export Excel juga hanya memetakan kunci itu, dan saringan pencarian
     * membuang kunci berawalan garis bawah dari bahan pencocokannya.
     */
    private const GAYA_JUDUL = 'judul';

    private const GAYA_RINGKASAN = 'ringkasan';

    /**
     * Satu baris ringkasan (sub total / total) untuk laporan COA.
     *
     * @return array<string, string>
     */
    private function barisRingkasanCoa(string $label, float $debit, float $kredit): array
    {
        return [
            'kode' => '',
            'nama_akun' => $label,
            'tipe' => '',
            'laporan' => '',
            'debit' => round($debit, 2) !== 0.0 ? $this->rupiah($debit, 2) : '-',
            'kredit' => round($kredit, 2) !== 0.0 ? $this->rupiah($kredit, 2) : '-',
            '_gaya' => self::GAYA_RINGKASAN,
        ];
    }

    /**
     * Baris judul bagian (AKTIVA / PASIVA / MODAL) — pemisah, bukan angka.
     *
     * @return array<string, string>
     */
    private function barisJudulCoa(string $label): array
    {
        return [
            'kode' => '',
            'nama_akun' => $label,
            'tipe' => '',
            'laporan' => '',
            'debit' => '',
            'kredit' => '',
            '_gaya' => self::GAYA_JUDUL,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function barisAkun(OpeningBalanceCoa $line): array
    {
        return [
            'kode' => $line->account->code ?? '-',
            'nama_akun' => $line->account->name ?? '-',
            'tipe' => $line->account->type ?? '-',
            'laporan' => ($line->account->statement ?? null) === 'NERACA' ? 'Neraca' : 'Laba/Rugi',
            'debit' => $line->position === 'debit' ? $this->rupiah((float) $line->amount, 2) : '-',
            'kredit' => $line->position === 'kredit' ? $this->rupiah((float) $line->amount, 2) : '-',
        ];
    }

    /**
     * Saldo Awal COA — Neraca (tersusun) dan Neraca Saldo (daftar rata).
     *
     * Keduanya diurutkan menurut nomor akun. Untuk Neraca, urutan itu saja
     * belum cukup: pembaca laporan keuangan mengharapkan Aktiva lebih dulu,
     * lalu Pasiva, lalu Modal, masing-masing dengan sub totalnya — bukan satu
     * daftar panjang yang harus dijumlah sendiri.
     *
     * Sub total dihitung NETO (debit dikurangi kredit) per kelompok, bukan
     * dijumlah per kolom. Itu perlu karena kelompok Aktiva memuat akun lawan
     * seperti akumulasi penyusutan dan penyisihan piutang yang bersaldo kredit;
     * menjumlahkannya di kolom kredit akan membuat Total Aktiva tampak lebih
     * besar dari yang sebenarnya.
     */
    private function saldoAwalCoa(bool $neracaSaja): Collection
    {
        // SORT_STRING wajib. Kode akun seluruhnya angka, dan pembanding bawaan
        // PHP memperlakukan dua string angka sebagai bilangan — "21081" lalu
        // dianggap lebih kecil dari "1101100", sehingga akun kewajiban lima
        // digit melompat ke atas seluruh akun aset tujuh digit. Dibandingkan
        // sebagai teks, urutannya mengikuti hierarki bagan akun sebagaimana
        // mestinya.
        $semua = OpeningBalanceCoa::query()
            ->with('account')
            ->get()
            ->sortBy(fn (OpeningBalanceCoa $line) => $line->account->code ?? '', SORT_STRING)
            ->values();

        // Laporan kosong dibiarkan benar-benar kosong — lihat catatan yang sama
        // di kelompokkanPerAnggota().
        if ($semua->isEmpty()) {
            return collect();
        }

        if (! $neracaSaja) {
            $rows = $semua->map(fn (OpeningBalanceCoa $line) => $this->barisAkun($line));

            return $rows->push($this->barisRingkasanCoa(
                'TOTAL NERACA SALDO',
                (float) $semua->where('position', 'debit')->sum('amount'),
                (float) $semua->where('position', 'kredit')->sum('amount'),
            ));
        }

        $neto = fn (Collection $lines) => (float) $lines->where('position', 'debit')->sum('amount')
            - (float) $lines->where('position', 'kredit')->sum('amount');

        $perTipe = $semua
            ->filter(fn (OpeningBalanceCoa $line) => ($line->account->statement ?? null) === 'NERACA')
            ->groupBy(fn (OpeningBalanceCoa $line) => $line->account->type ?? '-');

        $aset = $perTipe->get('ASET', collect());
        $liabilitas = $perTipe->get('LIABILITAS', collect());
        $ekuitas = $perTipe->get('EKUITAS', collect());

        // SHU berjalan: hasil usaha masih tersimpan di akun Laba/Rugi dan belum
        // ditutup ke ekuitas, sementara sisi Aset sudah memuatnya.
        $labaRugi = $semua->filter(fn (OpeningBalanceCoa $line) => ($line->account->statement ?? null) === 'LABA_RUGI');
        $shu = (float) $labaRugi->where('position', 'kredit')->sum('amount')
            - (float) $labaRugi->where('position', 'debit')->sum('amount');

        $rows = collect();

        $rows->push($this->barisJudulCoa('AKTIVA'));
        $aset->each(fn (OpeningBalanceCoa $line) => $rows->push($this->barisAkun($line)));
        $totalAktiva = $neto($aset);
        $rows->push($this->barisRingkasanCoa('SUB TOTAL AKTIVA', $totalAktiva, 0));

        $rows->push($this->barisJudulCoa('PASIVA (KEWAJIBAN)'));
        $liabilitas->each(fn (OpeningBalanceCoa $line) => $rows->push($this->barisAkun($line)));
        $totalPasiva = -$neto($liabilitas);
        $rows->push($this->barisRingkasanCoa('SUB TOTAL PASIVA', 0, $totalPasiva));

        $rows->push($this->barisJudulCoa('MODAL (EKUITAS)'));
        $ekuitas->each(fn (OpeningBalanceCoa $line) => $rows->push($this->barisAkun($line)));
        $totalModal = -$neto($ekuitas);

        if (round($shu, 2) !== 0.0) {
            $rows->push([
                'kode' => (string) config('koperasi.akun_shu_berjalan', ''),
                'nama_akun' => 'SHU TAHUN BERJALAN (dihitung dari akun Laba/Rugi)',
                'tipe' => 'EKUITAS',
                'laporan' => 'Neraca',
                'debit' => $shu < 0 ? $this->rupiah(abs($shu), 2) : '-',
                'kredit' => $shu > 0 ? $this->rupiah($shu, 2) : '-',
            ]);
            $totalModal += $shu;
        }

        $rows->push($this->barisRingkasanCoa('SUB TOTAL MODAL', 0, $totalModal));
        $rows->push($this->barisRingkasanCoa('TOTAL PASIVA + MODAL', 0, $totalPasiva + $totalModal));

        return $rows->push($this->barisRingkasanCoa('TOTAL NERACA', $totalAktiva, $totalPasiva + $totalModal));
    }

    /**
     * Kelompokkan per anggota lalu sisipkan baris sub total di ujung tiap
     * kelompok.
     *
     * Baris sub total ikut jadi pembatas antar anggota — pada laporan sepanjang
     * ribuan baris, batas itu yang membuat cetakannya bisa ditelusuri. Untuk
     * saldo awal, sub total hanya muncul bila anggotanya punya lebih dari satu
     * baris; menambahkannya pada anggota berbaris tunggal hanya menggandakan
     * angka yang sama tepat di bawahnya.
     *
     * @return Collection<int, array<string, string>>
     */
    private function kelompokkanPerAnggota(
        Collection $records,
        callable $kunciAnggota,
        callable $formatBaris,
        callable $formatSubTotal,
        bool $subTotalHanyaBilaGanda = false,
    ): Collection {
        $rows = collect();

        // Laporan kosong dibiarkan benar-benar kosong; baris TOTAL berisi nol
        // hanya menyamarkan "belum ada data" jadi seolah ada isinya.
        if ($records->isEmpty()) {
            return $rows;
        }

        foreach ($records->groupBy($kunciAnggota) as $anggota) {
            $anggota->each(fn ($record) => $rows->push($formatBaris($record)));

            if (! $subTotalHanyaBilaGanda || $anggota->count() > 1) {
                $rows->push($formatSubTotal($anggota, $anggota->count()));
            }
        }

        return $rows;
    }

    private function saldoAwalPinjaman(): Collection
    {
        $records = OpeningBalanceLoan::query()
            ->with(['member', 'loanProduct'])
            ->get()
            ->sortBy([
                fn (OpeningBalanceLoan $a, OpeningBalanceLoan $b) => strcmp((string) $a->member?->member_number, (string) $b->member?->member_number),
                fn (OpeningBalanceLoan $a, OpeningBalanceLoan $b) => strcmp((string) $a->external_loan_number, (string) $b->external_loan_number),
            ])
            ->values();

        $kosong = array_fill_keys(array_keys(LaporanRegistry::columnsFor('saldo_awal_pinjaman')), '');

        $rows = $this->kelompokkanPerAnggota(
            $records,
            fn (OpeningBalanceLoan $row) => $row->member_id,
            fn (OpeningBalanceLoan $row) => [
                'kode_anggota' => $row->member->member_number ?? '-',
                'nama_anggota' => $row->member->name ?? '-',
                'no_pinjaman_lama' => $row->external_loan_number ?? '-',
                'produk' => $row->loanProduct->name ?? '-',
                'tanggal_akad' => optional($row->disbursement_date)->format('d-m-Y') ?? '-',
                'plafon_awal' => $this->rupiah((float) $row->original_principal),
                'sisa_pokok' => $this->rupiah((float) $row->outstanding_principal),
                'sisa_jasa' => $this->rupiah((float) $row->outstanding_interest),
                'tenor' => (string) $row->tenor_days,
                'sisa_tenor' => (string) $row->remaining_tenor_days,
                'jatuh_tempo' => optional($row->next_due_date)->format('d-m-Y') ?? '-',
                'kolektibilitas' => ucwords(str_replace('_', ' ', (string) $row->collectibility)),
            ],
            fn (Collection $grup, int $jumlah) => [
                ...$kosong,
                'nama_anggota' => 'SUB TOTAL '.($grup->first()->member->name ?? '-'),
                '_gaya' => self::GAYA_RINGKASAN,
                'no_pinjaman_lama' => $jumlah.' pinjaman',
                'plafon_awal' => $this->rupiah((float) $grup->sum('original_principal')),
                'sisa_pokok' => $this->rupiah((float) $grup->sum('outstanding_principal')),
                'sisa_jasa' => $this->rupiah((float) $grup->sum('outstanding_interest')),
            ],
            subTotalHanyaBilaGanda: true,
        );

        return $rows->isEmpty() ? $rows : $rows->push([
            ...$kosong,
            'nama_anggota' => 'TOTAL SELURUH ANGGOTA',
            '_gaya' => self::GAYA_RINGKASAN,
            'no_pinjaman_lama' => $records->count().' pinjaman',
            'plafon_awal' => $this->rupiah((float) $records->sum('original_principal')),
            'sisa_pokok' => $this->rupiah((float) $records->sum('outstanding_principal')),
            'sisa_jasa' => $this->rupiah((float) $records->sum('outstanding_interest')),
        ]);
    }

    private function saldoAwalSimpanan(): Collection
    {
        $records = OpeningBalanceSaving::query()
            ->with(['member', 'savingsProduct'])
            ->get()
            ->sortBy([
                fn (OpeningBalanceSaving $a, OpeningBalanceSaving $b) => strcmp((string) $a->member?->member_number, (string) $b->member?->member_number),
                fn (OpeningBalanceSaving $a, OpeningBalanceSaving $b) => strcmp((string) $a->savingsProduct?->code, (string) $b->savingsProduct?->code),
            ])
            ->values();

        $kosong = array_fill_keys(array_keys(LaporanRegistry::columnsFor('saldo_awal_simpanan')), '');

        $rows = $this->kelompokkanPerAnggota(
            $records,
            fn (OpeningBalanceSaving $row) => $row->member_id,
            fn (OpeningBalanceSaving $row) => [
                'kode_anggota' => $row->member->member_number ?? '-',
                'nama_anggota' => $row->member->name ?? '-',
                'produk' => $row->savingsProduct->name ?? '-',
                'no_rekening' => $row->account_number ?: '-',
                'saldo_awal' => $this->rupiah((float) $row->balance),
                'keterangan' => $row->notes ?: '-',
            ],
            fn (Collection $grup, int $jumlah) => [
                ...$kosong,
                'nama_anggota' => 'SUB TOTAL '.($grup->first()->member->name ?? '-'),
                '_gaya' => self::GAYA_RINGKASAN,
                'produk' => $jumlah.' rekening',
                'saldo_awal' => $this->rupiah((float) $grup->sum('balance')),
            ],
            subTotalHanyaBilaGanda: true,
        );

        return $rows->isEmpty() ? $rows : $rows->push([
            ...$kosong,
            'nama_anggota' => 'TOTAL SELURUH ANGGOTA',
            '_gaya' => self::GAYA_RINGKASAN,
            'produk' => $records->count().' rekening',
            'saldo_awal' => $this->rupiah((float) $records->sum('balance')),
        ]);
    }

    /**
     * Hanya baris bertanda migrasi. Pembayaran yang benar-benar terjadi di
     * aplikasi ini punya laporannya sendiri ("Transaksi Pembayaran Angsuran");
     * mencampur keduanya di sini akan membuat rekonsiliasi terhadap sistem
     * lama menghitung transaksi yang bukan bagian dari migrasi.
     */
    private function migrasiHistorisPembayaran(): Collection
    {
        $records = LoanRepayment::query()
            ->whereNotNull('migrated_at')
            ->with(['loan.member'])
            ->get()
            ->sortBy([
                fn (LoanRepayment $a, LoanRepayment $b) => strcmp((string) $a->loan?->member?->member_number, (string) $b->loan?->member?->member_number),
                fn (LoanRepayment $a, LoanRepayment $b) => strcmp((string) $a->loan?->loan_number, (string) $b->loan?->loan_number),
                fn (LoanRepayment $a, LoanRepayment $b) => $a->paidOn()->timestamp <=> $b->paidOn()->timestamp,
            ])
            ->values();

        $kosong = array_fill_keys(array_keys(LaporanRegistry::columnsFor('migrasi_historis_pembayaran')), '');

        $rows = $this->kelompokkanPerAnggota(
            $records,
            fn (LoanRepayment $row) => $row->loan?->member_id,
            fn (LoanRepayment $row) => [
                'tanggal' => $row->paidOn()->format('d-m-Y'),
                'no_pinjaman' => $row->loan->loan_number ?? '-',
                'nama_anggota' => $row->loan->member->name ?? '-',
                'pokok' => $this->rupiah((float) $row->principal_portion),
                'jasa' => $this->rupiah((float) $row->interest_portion),
                'jumlah' => $this->rupiah((float) $row->amount),
                'sisa' => $this->rupiah((float) $row->balance_after),
                'keterangan' => $row->description ?: '-',
            ],
            fn (Collection $grup, int $jumlah) => [
                ...$kosong,
                'nama_anggota' => 'SUB TOTAL '.($grup->first()->loan->member->name ?? '-'),
                '_gaya' => self::GAYA_RINGKASAN,
                'no_pinjaman' => $jumlah.' pembayaran',
                'pokok' => $this->rupiah((float) $grup->sum('principal_portion')),
                'jasa' => $this->rupiah((float) $grup->sum('interest_portion')),
                'jumlah' => $this->rupiah((float) $grup->sum('amount')),
            ],
        );

        return $rows->isEmpty() ? $rows : $rows->push([
            ...$kosong,
            'nama_anggota' => 'TOTAL SELURUH ANGGOTA',
            '_gaya' => self::GAYA_RINGKASAN,
            'no_pinjaman' => $records->count().' pembayaran',
            'pokok' => $this->rupiah((float) $records->sum('principal_portion')),
            'jasa' => $this->rupiah((float) $records->sum('interest_portion')),
            'jumlah' => $this->rupiah((float) $records->sum('amount')),
        ]);
    }

    private function migrasiJadwalPembayaran(): Collection
    {
        $records = LoanSchedule::query()
            ->whereHas('loan')
            ->with(['loan.member'])
            ->get()
            ->sortBy([
                fn (LoanSchedule $a, LoanSchedule $b) => strcmp((string) $a->loan?->member?->member_number, (string) $b->loan?->member?->member_number),
                fn (LoanSchedule $a, LoanSchedule $b) => strcmp((string) $a->loan?->loan_number, (string) $b->loan?->loan_number),
                fn (LoanSchedule $a, LoanSchedule $b) => $a->installment_number <=> $b->installment_number,
            ])
            ->values();

        $kosong = array_fill_keys(array_keys(LaporanRegistry::columnsFor('migrasi_jadwal_pembayaran')), '');
        $sisa = fn (LoanSchedule $row) => (float) $row->total_amount - (float) $row->paid_amount;

        $rows = $this->kelompokkanPerAnggota(
            $records,
            fn (LoanSchedule $row) => $row->loan?->member_id,
            fn (LoanSchedule $row) => [
                'no_pinjaman' => $row->loan->loan_number ?? '-',
                'nama_anggota' => $row->loan->member->name ?? '-',
                'angsuran_ke' => (string) $row->installment_number,
                'jatuh_tempo' => $row->due_date->format('d-m-Y'),
                'pokok' => $this->rupiah((float) $row->principal_amount),
                'jasa' => $this->rupiah((float) $row->interest_amount),
                'total' => $this->rupiah((float) $row->total_amount),
                'terbayar' => $this->rupiah((float) $row->paid_amount),
                'sisa' => $this->rupiah($sisa($row)),
                'status' => ucwords(str_replace('_', ' ', (string) $row->status)),
            ],
            fn (Collection $grup, int $jumlah) => [
                ...$kosong,
                'nama_anggota' => 'SUB TOTAL '.($grup->first()->loan->member->name ?? '-'),
                '_gaya' => self::GAYA_RINGKASAN,
                'angsuran_ke' => (string) $jumlah,
                'pokok' => $this->rupiah((float) $grup->sum('principal_amount')),
                'jasa' => $this->rupiah((float) $grup->sum('interest_amount')),
                'total' => $this->rupiah((float) $grup->sum('total_amount')),
                'terbayar' => $this->rupiah((float) $grup->sum('paid_amount')),
                'sisa' => $this->rupiah((float) $grup->sum($sisa)),
            ],
        );

        return $rows->isEmpty() ? $rows : $rows->push([
            ...$kosong,
            'nama_anggota' => 'TOTAL SELURUH ANGGOTA',
            '_gaya' => self::GAYA_RINGKASAN,
            'angsuran_ke' => (string) $records->count(),
            'pokok' => $this->rupiah((float) $records->sum('principal_amount')),
            'jasa' => $this->rupiah((float) $records->sum('interest_amount')),
            'total' => $this->rupiah((float) $records->sum('total_amount')),
            'terbayar' => $this->rupiah((float) $records->sum('paid_amount')),
            'sisa' => $this->rupiah((float) $records->sum($sisa)),
        ]);
    }
    /**
     * Laporan operasional dibulatkan ke rupiah penuh supaya kolomnya ringkas.
     * Neraca dan Neraca Saldo memakai dua angka di belakang koma: saldo awal
     * bawaan sistem lama menyimpan sen, dan kalau dibulatkan, jumlah kolomnya
     * bisa meleset beberapa rupiah dari sub total -- pembaca laporan keuangan
     * akan membacanya sebagai neraca yang tidak balance.
     */
    private function rupiah(float|string $amount, int $desimal = 0): string
    {
        return 'Rp '.number_format((float) $amount, $desimal, ',', '.');
    }

    private function anggota(): Collection
    {
        return Member::query()
            ->with(['memberType', 'branch'])
            ->orderBy('name')
            ->get()
            ->map(fn (Member $member) => [
                'member_number' => $member->member_number,
                'name' => $member->name,
                'jenis' => $member->memberType->name ?? '-',
                'cabang' => $member->branch->name ?? '-',
                'status' => ucfirst($member->status),
                'joined_at' => optional($member->joined_at)->format('d-m-Y') ?? '-',
            ]);
    }

    private function jenisAnggota(): Collection
    {
        return MemberType::query()->orderBy('name')->get()->map(fn (MemberType $type) => [
            'code' => $type->code,
            'name' => $type->name,
            'hak_suara' => $type->has_voting_rights ? 'Ya' : 'Tidak',
            'wajib_simpanan' => $type->requires_mandatory_savings ? 'Ya' : 'Tidak',
            'nominal_wajib' => $this->rupiah((float) $type->mandatory_savings_default_amount),
            'status' => $type->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function baganAkun(): Collection
    {
        return ChartOfAccount::query()->orderBy('code')->get()->map(fn (ChartOfAccount $account) => [
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'group' => $account->group,
            'normal_balance' => ucfirst($account->normal_balance),
            'is_postable' => $account->is_postable ? 'Ya' : 'Tidak',
        ]);
    }

    private function simpananRekening(bool $hanyaBersaldo = false): Collection
    {
        return SavingsAccount::query()
            ->when($hanyaBersaldo, fn ($query) => $query->where('balance', '>', 0))
            ->with(['member', 'savingsProduct', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (SavingsAccount $account) => [
                'account_number' => $account->account_number,
                'member_name' => $account->member->name ?? '-',
                'produk' => $account->savingsProduct->name ?? '-',
                'cabang' => $account->branch->name ?? '-',
                'balance' => $this->rupiah((float) $account->balance),
                'status' => ucfirst($account->status),
                'opened_at' => optional($account->opened_at)->format('d-m-Y') ?? '-',
            ]);
    }

    private function simpananTransaksi(): Collection
    {
        return SavingsTransaction::query()
            ->with(['savingsAccount.member', 'branch'])
            ->latest('id')
            ->get()
            ->map(fn (SavingsTransaction $trx) => [
                'tanggal' => $trx->created_at->format('d-m-Y H:i'),
                'account_number' => $trx->savingsAccount->account_number ?? '-',
                'member_name' => $trx->savingsAccount->member->name ?? '-',
                'jenis' => ucfirst($trx->type),
                'amount' => $this->rupiah((float) $trx->amount),
                'balance_after' => $this->rupiah((float) $trx->balance_after),
                'cabang' => $trx->branch->name ?? '-',
                'status' => $trx->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    /**
     * `loans` tidak menyimpan kolom sisa pokok — sisa dihitung dari jadwal
     * angsuran. Untuk saringan cetak dipakai status `lunas`, yang justru lebih
     * jujur di sini: status itu satu-satunya penanda pelunasan yang ikut
     * tercetak, jadi pembaca bisa melihat sendiri kenapa baris tertentu absen.
     */
    private function pinjaman(bool $hanyaBersaldo = false): Collection
    {
        return Loan::query()
            ->when($hanyaBersaldo, fn ($query) => $query->where('status', '!=', 'lunas'))
            ->with(['member', 'loanProduct', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $loan) => [
                'loan_number' => $loan->loan_number,
                'member_name' => $loan->member->name ?? '-',
                'produk' => $loan->loanProduct->name ?? '-',
                'cabang' => $loan->branch->name ?? '-',
                'principal_amount' => $this->rupiah((float) $loan->principal_amount),
                'tenor_months' => $loan->tenorLabel(),
                'status' => ucfirst($loan->status),
                'collectibility' => (string) $loan->collectibility,
                'disbursed_at' => optional($loan->disbursed_at)->format('d-m-Y') ?? '-',
            ]);
    }

    private function angsuran(): Collection
    {
        return LoanSchedule::query()
            ->whereHas('loan')
            ->with(['loan.member'])
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (LoanSchedule $schedule) => [
                'loan_number' => $schedule->loan->loan_number ?? '-',
                'member_name' => $schedule->loan->member->name ?? '-',
                'installment_number' => (string) $schedule->installment_number,
                'due_date' => $schedule->due_date->format('d-m-Y'),
                'total_amount' => $this->rupiah((float) $schedule->total_amount),
                'paid_amount' => $this->rupiah((float) $schedule->paid_amount),
                'status' => ucfirst($schedule->status),
            ]);
    }

    private function transaksiPinjaman(): Collection
    {
        return Loan::query()
            ->whereNotNull('disbursed_at')
            ->with(['member', 'loanProduct', 'branch'])
            ->orderByDesc('disbursed_at')
            ->get()
            ->map(fn (Loan $loan) => [
                'loan_number' => $loan->loan_number,
                'member_name' => $loan->member->name ?? '-',
                'produk' => $loan->loanProduct->name ?? '-',
                'cabang' => $loan->branch->name ?? '-',
                'tanggal_cair' => $loan->disbursed_at->format('d-m-Y'),
                'jumlah' => $this->rupiah((float) $loan->principal_amount),
            ]);
    }

    private function pembayaranAngsuran(): Collection
    {
        return LoanRepayment::query()
            ->with(['loan.member', 'branch'])
            ->latest('id')
            ->get()
            ->map(fn (LoanRepayment $repayment) => [
                'tanggal' => $repayment->paidOn()->format('d-m-Y'),
                'loan_number' => $repayment->loan->loan_number ?? '-',
                'member_name' => $repayment->loan->member->name ?? '-',
                'cabang' => $repayment->branch->name ?? '-',
                'jumlah' => $this->rupiah((float) $repayment->amount),
                'pokok' => $this->rupiah((float) $repayment->principal_portion),
                'jasa' => $this->rupiah((float) $repayment->interest_portion),
                'saldo_akhir' => $this->rupiah((float) $repayment->balance_after),
                'status' => $repayment->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function pengajuanPersetujuanPinjaman(): Collection
    {
        $decisionLabels = ['setuju' => 'Disetujui', 'tolak' => 'Ditolak'];

        return LoanApproval::query()
            ->whereHas('loan')
            ->with(['loan.member', 'approvedBy'])
            ->latest('decided_at')
            ->get()
            ->map(fn (LoanApproval $approval) => [
                'tanggal' => optional($approval->decided_at)->format('d-m-Y H:i') ?? '-',
                'loan_number' => $approval->loan->loan_number ?? '-',
                'member_name' => $approval->loan->member->name ?? '-',
                'penyetuju' => $approval->approvedBy->name ?? '-',
                'keputusan' => $decisionLabels[$approval->decision] ?? ucfirst($approval->decision),
                'catatan' => $approval->notes ?: '-',
            ]);
    }

    private function barang(): Collection
    {
        return Product::query()->orderBy('name')->get()->map(fn (Product $product) => [
            'code' => $product->code,
            'name' => $product->name,
            'category' => $product->category ?: '-',
            'unit' => $product->unit,
            'purchase_price' => $this->rupiah((float) $product->purchase_price),
            'selling_price' => $this->rupiah((float) $product->selling_price),
            'status' => $product->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function supplier(): Collection
    {
        return Supplier::query()->orderBy('name')->get()->map(fn (Supplier $supplier) => [
            'code' => $supplier->code,
            'name' => $supplier->name,
            'type' => ucfirst($supplier->type),
            'payment_term' => $supplier->isKredit() ? "Kredit {$supplier->payment_term_days} hari" : 'Tunai',
            'status' => $supplier->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function pembelian(): Collection
    {
        return PurchaseTransaction::query()
            ->with(['supplier', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseTransaction $purchase) => [
                'purchase_number' => $purchase->purchase_number,
                'tanggal' => $purchase->purchase_date->format('d-m-Y'),
                'supplier_name' => $purchase->supplier->name ?? '-',
                'cabang' => $purchase->branch->name ?? '-',
                'total_amount' => $this->rupiah((float) $purchase->total_amount),
                'payment_method' => ucfirst($purchase->payment_method),
                'status' => ucfirst($purchase->status),
                'payment_status' => ucfirst($purchase->payment_status),
            ]);
    }

    private function returPembelian(): Collection
    {
        return PurchaseReturn::query()
            ->with(['purchaseItem.product', 'purchaseItem.purchaseTransaction.supplier', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseReturn $return) => [
                'tanggal' => $return->return_date->format('d-m-Y'),
                'no_pembelian' => $return->purchaseItem->purchaseTransaction->purchase_number ?? '-',
                'barang' => $return->purchaseItem->product->name ?? '-',
                'supplier_name' => $return->purchaseItem->purchaseTransaction->supplier->name ?? '-',
                'qty' => (string) $return->qty,
                'amount' => $this->rupiah((float) $return->amount),
                'cabang' => $return->branch->name ?? '-',
            ]);
    }

    private function koreksiPersediaan(): Collection
    {
        return StockAdjustment::query()
            ->with(['product', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockAdjustment $adjustment) => [
                'tanggal' => $adjustment->adjustment_date->format('d-m-Y'),
                'barang' => $adjustment->product->name ?? '-',
                'cabang' => $adjustment->branch->name ?? '-',
                'system_qty' => (string) $adjustment->system_qty,
                'physical_qty' => (string) $adjustment->physical_qty,
                'variance_qty' => (string) $adjustment->variance_qty,
                'amount' => $this->rupiah((float) $adjustment->amount),
                'status' => $adjustment->isCancelled() ? 'Dibatalkan' : ucfirst($adjustment->status),
            ]);
    }

    /**
     * Format kartu buku besar: dikelompokkan per barang+cabang (baris
     * dalam satu grup kontinu, urut kronologis, karena datatable generik
     * di hub tidak meregrup ulang di sisi klien), dengan baris "Saldo Awal"
     * sintetis di depan grup (hanya jika belum ada baris riwayat migrasi
     * `saldo_awal` — lihat OpeningBalanceLockService::materializeStock())
     * dan baris "Saldo Akhir" di penutup setiap grup — meniru
     * jurnal-buku-besar.blade.php (Tanggal/Keterangan/Debit/Kredit/Saldo).
     */
    private function persediaanKartu(): Collection
    {
        $groups = StockLedgerEntry::query()
            ->with(['product', 'branch'])
            ->orderBy('product_id')
            ->orderBy('branch_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StockLedgerEntry $entry) => $entry->product_id.'-'.$entry->branch_id);

        $rows = collect();

        foreach ($groups as $group) {
            $first = $group->first();
            $barang = $first->product->name ?? '-';
            $cabang = $first->branch->name ?? '-';

            if ($first->transaction_type !== 'saldo_awal') {
                $rows->push([
                    'tanggal' => $first->transaction_date->format('d-m-Y'),
                    'barang' => $barang,
                    'cabang' => $cabang,
                    'jenis' => 'Saldo Awal',
                    'debet' => '-',
                    'kredit' => '-',
                    'harga' => '-',
                    'saldo' => '0.0000',
                ]);
            }

            foreach ($group as $entry) {
                $rows->push([
                    'tanggal' => $entry->transaction_date->format('d-m-Y'),
                    'barang' => $barang,
                    'cabang' => $cabang,
                    'jenis' => $entry->transaction_type === 'saldo_awal' ? 'Saldo Awal' : ucfirst(str_replace('_', ' ', $entry->transaction_type)),
                    'debet' => (string) $entry->qty_in,
                    'kredit' => (string) $entry->qty_out,
                    'harga' => $this->rupiah((float) $entry->unit_cost),
                    'saldo' => (string) $entry->running_qty,
                ]);
            }

            $last = $group->last();
            $rows->push([
                'tanggal' => $last->transaction_date->format('d-m-Y'),
                'barang' => $barang,
                'cabang' => $cabang,
                'jenis' => 'Saldo Akhir',
                'debet' => '-',
                'kredit' => '-',
                'harga' => '-',
                'saldo' => (string) $last->running_qty,
            ]);
        }

        return $rows;
    }

    private function saldoPersediaan(): Collection
    {
        $allowed = auth()->user()->allowedBranchIds();
        $branchId = $allowed === null ? null : ($allowed[0] ?? null);

        return $this->inventoryReportService->saldoPersediaan($branchId)
            ->map(fn (array $row) => [
                'kode' => $row['product']->code ?? '-',
                'barang' => $row['product']->name ?? '-',
                'qty' => $row['qty'],
                'nilai' => $this->rupiah((float) $row['value']),
            ]);
    }

    private function kategoriAktivaTetap(): Collection
    {
        return FixedAssetCategory::query()->orderBy('name')->get()->map(fn (FixedAssetCategory $category) => [
            'code' => $category->code,
            'name' => $category->name,
            'default_depreciation_method' => ucfirst($category->default_depreciation_method),
            'default_useful_life_months' => (string) $category->default_useful_life_months,
            'status' => $category->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function aktivaTetap(): Collection
    {
        return FixedAsset::query()
            ->with(['category', 'branch'])
            ->orderBy('code')
            ->get()
            ->map(fn (FixedAsset $asset) => [
                'code' => $asset->code,
                'name' => $asset->name,
                'kategori' => $asset->category->name ?? '-',
                'cabang' => $asset->branch->name ?? '-',
                'acquisition_date' => $asset->acquisition_date->format('d-m-Y'),
                'acquisition_cost' => $this->rupiah((float) $asset->acquisition_cost),
                'akumulasi_penyusutan' => $this->rupiah((float) $asset->accumulatedDepreciation()),
                'book_value' => $this->rupiah((float) $asset->bookValue()),
                'status' => ucfirst($asset->status),
            ]);
    }

    private function pelepasanAktivaTetap(): Collection
    {
        return FixedAssetDisposal::query()
            ->with('fixedAsset')
            ->orderByDesc('disposal_date')
            ->get()
            ->map(fn (FixedAssetDisposal $disposal) => [
                'tanggal' => $disposal->disposal_date->format('d-m-Y'),
                'kode' => $disposal->fixedAsset->code ?? '-',
                'nama' => $disposal->fixedAsset->name ?? '-',
                'jenis_pelepasan' => ucfirst($disposal->disposal_type),
                'nilai_jual' => $this->rupiah((float) $disposal->sale_amount),
                'nilai_buku' => $this->rupiah((float) $disposal->book_value_at_disposal),
                'untung_rugi' => $this->rupiah((float) $disposal->gain_loss_amount),
                'status' => $disposal->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function jenisRetribusi(): Collection
    {
        return RetributionType::query()->orderBy('sort_order')->get()->map(fn (RetributionType $type) => [
            'code' => $type->code,
            'name' => $type->name,
            'percentage' => number_format((float) $type->percentage, 2).'%',
            'status' => $type->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function retribusiUpf(): Collection
    {
        return RetributionTransaction::query()
            ->with('branch')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RetributionTransaction $trx) => [
                'tanggal' => $trx->transaction_date->format('d-m-Y'),
                'transaction_number' => $trx->transaction_number,
                'payer_name' => $trx->payer_name,
                'payer_type' => ucfirst($trx->payer_type),
                'cabang' => $trx->branch->name ?? '-',
                'total_amount' => $this->rupiah((float) $trx->total_amount),
                'payment_method' => ucfirst($trx->payment_method),
                'status' => $trx->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function unitUsaha(): Collection
    {
        return BusinessUnit::query()->orderBy('name')->get()->map(fn (BusinessUnit $unit) => [
            'code' => $unit->code,
            'name' => $unit->name,
            'status' => $unit->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function transaksiUnitUsaha(): Collection
    {
        return BusinessUnitTransaction::query()
            ->with(['businessUnit', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (BusinessUnitTransaction $trx) => [
                'tanggal' => $trx->created_at->format('d-m-Y'),
                'unit_usaha' => $trx->businessUnit->name ?? '-',
                'cabang' => $trx->branch->name ?? '-',
                'jenis' => ucfirst($trx->type),
                'amount' => $this->rupiah((float) $trx->amount),
                'description' => $trx->description ?: '-',
            ]);
    }

    private function transaksiPos(): Collection
    {
        return PosSale::query()
            ->with('branch')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PosSale $sale) => [
                'tanggal' => $sale->sale_date->format('d-m-Y'),
                'sale_number' => $sale->sale_number,
                'cabang' => $sale->branch->name ?? '-',
                'metode_bayar' => ucfirst(str_replace('_', ' ', $sale->payment_method)),
                'total' => $this->rupiah((float) $sale->total_amount),
            ]);
    }

    private function transaksiTeller(): Collection
    {
        return TellerCashTransaction::query()
            ->with(['category', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (TellerCashTransaction $trx) => [
                'tanggal' => $trx->created_at->format('d-m-Y H:i'),
                'kategori' => $trx->category->name ?? '-',
                'akun_kas' => $trx->cashAccount->name ?? '-',
                'cabang' => $trx->branch->name ?? '-',
                'jumlah' => $this->rupiah((float) $trx->amount),
                'keterangan' => $trx->description ?: '-',
            ]);
    }

    private function kalenderKegiatan(): Collection
    {
        $allowed = auth()->user()->allowedBranchIds();
        $statusLabels = ['terjadwal' => 'Terjadwal', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'];

        return CooperativeEvent::query()
            ->orderByDesc('start_at')
            ->get()
            ->filter(function (CooperativeEvent $event) use ($allowed) {
                if ($allowed === null) {
                    return true;
                }

                $ids = $event->branchIds();

                return $ids === null || array_intersect($ids, $allowed) !== [];
            })
            ->map(fn (CooperativeEvent $event) => [
                'tanggal' => $event->start_at->format('d-m-Y H:i'),
                'title' => $event->title,
                'location' => $event->location ?: '-',
                'status' => $statusLabels[$event->status] ?? ucfirst($event->status),
            ])
            ->values();
    }

    private function jurnalUmum(): Collection
    {
        return JournalEntry::query()
            ->with(['branch', 'createdBy', 'lines'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'tanggal' => $entry->entry_date->format('d-m-Y'),
                'description' => $entry->description,
                'cabang' => $entry->branch->name ?? '-',
                'source_type' => $entry->source_type ? class_basename($entry->source_type) : 'Manual',
                'total_debit' => $this->rupiah((float) $entry->lines->sum('debit')),
                'total_kredit' => $this->rupiah((float) $entry->lines->sum('credit')),
                'created_by_name' => $entry->createdBy->name ?? '-',
            ]);
    }

    private function pengguna(): Collection
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->map(
                    fn (string $role) => ucfirst(str_replace('_', ' ', $role))
                )->implode(', ') ?: '-',
                'status' => $user->is_active ? 'Aktif' : 'Nonaktif',
            ]);
    }
}

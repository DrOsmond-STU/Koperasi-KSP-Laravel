<?php

namespace Tests\Feature\Loans;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian dan saringan di layar antrian persetujuan.
 *
 * Satu bilah menyaring KEDUA tabel sekaligus. Itu disengaja: staf yang
 * mengejar satu nomor pinjaman belum tentu tahu pinjaman itu masih
 * menunggu persetujuan atau sudah cair, dan memaksanya menebak lebih dulu
 * hanya memindahkan pekerjaan ke orangnya.
 *
 * Tabel bawah dulu dipotong di 20 baris tanpa memberi tahu siapa pun.
 * Digabung dengan pencarian, pemotongan diam-diam itu berubah dari
 * merepotkan jadi menyesatkan — staf mencari satu nomor, tidak melihatnya,
 * lalu menyimpulkan datanya hilang. Karena itu dipaginasi dan jumlah
 * totalnya ditulis di layar.
 */
class LoanQueueSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function pengurus(): User
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole('manajer');
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function pinjaman(array $ubah = [], ?string $namaAnggota = null, ?string $nomorAnggota = null): Loan
    {
        $member = Member::factory()->create(array_filter([
            'name' => $namaAnggota,
            'member_number' => $nomorAnggota,
        ]));

        return Loan::factory()->create(array_merge([
            'member_id' => $member->id,
            'created_by' => User::factory()->create()->id,
            'status' => 'diajukan',
            'submitted_at' => '2026-08-13',
        ], $ubah));
    }

    private function layar(array $saringan = [])
    {
        return $this->actingAs($this->pengurus())
            ->get(route('admin.pinjaman.index', $saringan))
            ->assertOk();
    }

    public function test_searching_by_loan_number_narrows_the_queue(): void
    {
        $dicari = $this->pinjaman(['loan_number' => 'PINJ-AAA-111']);
        $lainnya = $this->pinjaman(['loan_number' => 'PINJ-BBB-222']);

        $this->layar(['cari' => 'AAA'])
            ->assertSee('PINJ-AAA-111')
            ->assertDontSee('PINJ-BBB-222');

        $this->assertNotSame($dicari->id, $lainnya->id);
    }

    public function test_searching_by_member_name_works(): void
    {
        $this->pinjaman(['loan_number' => 'PINJ-MUT-001'], 'MUTAR');
        $this->pinjaman(['loan_number' => 'PINJ-SIT-002'], 'Siti Aminah');

        $this->layar(['cari' => 'mutar'])
            ->assertSee('PINJ-MUT-001')
            ->assertDontSee('PINJ-SIT-002');
    }

    public function test_searching_by_member_number_works(): void
    {
        $this->pinjaman(['loan_number' => 'PINJ-NOM-001'], null, 'AGT-9911');
        $this->pinjaman(['loan_number' => 'PINJ-NOM-002'], null, 'AGT-2200');

        $this->layar(['cari' => '9911'])
            ->assertSee('PINJ-NOM-001')
            ->assertDontSee('PINJ-NOM-002');
    }

    /** Inti rancangannya: satu kali mengetik mencari di antrian DAN di daftar cair. */
    public function test_one_search_box_covers_both_tables(): void
    {
        $this->pinjaman(['loan_number' => 'PINJ-CARI-MENUNGGU'], 'Rahayu Sedjati');
        $this->pinjaman([
            'loan_number' => 'PINJ-CARI-CAIR',
            'status' => 'dicairkan',
            'disbursed_at' => '2026-08-20',
        ], 'Rahayu Sedjati');

        $this->layar(['cari' => 'Rahayu'])
            ->assertSee('PINJ-CARI-MENUNGGU')
            ->assertSee('PINJ-CARI-CAIR');
    }

    public function test_filtering_by_product_works(): void
    {
        $produkA = LoanProduct::factory()->create(['name' => 'Pinjaman Kilat']);
        $produkB = LoanProduct::factory()->create(['name' => 'Pinjaman Musiman']);

        $this->pinjaman(['loan_number' => 'PINJ-PROD-A', 'loan_product_id' => $produkA->id]);
        $this->pinjaman(['loan_number' => 'PINJ-PROD-B', 'loan_product_id' => $produkB->id]);

        $this->layar(['produk' => $produkA->id])
            ->assertSee('PINJ-PROD-A')
            ->assertDontSee('PINJ-PROD-B');
    }

    public function test_filtering_by_branch_works(): void
    {
        $usp = Branch::factory()->create(['code' => '002', 'name' => 'Unit Simpan Pinjam']);
        $ksp = Branch::factory()->create(['code' => '001', 'name' => 'Kantor Pusat']);

        $this->pinjaman(['loan_number' => 'PINJ-USP-001', 'branch_id' => $usp->id]);
        $this->pinjaman(['loan_number' => 'PINJ-KSP-001', 'branch_id' => $ksp->id]);

        $this->layar(['cabang' => $usp->id])
            ->assertSee('PINJ-USP-001')
            ->assertDontSee('PINJ-KSP-001');
    }

    /** Status hanya mengenai tabel bawah — tabel atas menurut definisinya satu status. */
    public function test_the_status_filter_separates_cancelled_from_disbursed(): void
    {
        $this->pinjaman([
            'loan_number' => 'PINJ-CAIR-HIDUP',
            'status' => 'dicairkan',
            'disbursed_at' => '2026-08-20',
        ]);
        $this->pinjaman([
            'loan_number' => 'PINJ-CAIR-BATAL',
            'status' => 'dibatalkan',
            'disbursed_at' => '2026-08-20',
            'cancelled_at' => now(),
        ]);

        $this->layar(['status' => 'dibatalkan'])
            ->assertSee('PINJ-CAIR-BATAL')
            ->assertDontSee('PINJ-CAIR-HIDUP');

        $this->layar(['status' => 'dicairkan'])
            ->assertSee('PINJ-CAIR-HIDUP')
            ->assertDontSee('PINJ-CAIR-BATAL');
    }

    /** Saringan tak dikenal tidak boleh menyelinap ke where('status', ...). */
    public function test_an_unknown_status_is_ignored_rather_than_hiding_everything(): void
    {
        $this->pinjaman([
            'loan_number' => 'PINJ-TETAP-TAMPIL',
            'status' => 'dicairkan',
            'disbursed_at' => '2026-08-20',
        ]);

        $this->layar(['status' => 'diajukan'])->assertSee('PINJ-TETAP-TAMPIL');
        $this->layar(['status' => 'sembarang'])->assertSee('PINJ-TETAP-TAMPIL');
    }

    /**
     * Yang paling penting dari seluruh berkas ini: hasil yang terpotong harus
     * mengaku terpotong. Tanpa jumlah total dan tautan halaman, pencarian
     * justru menciptakan kepercayaan palsu bahwa data tidak ada.
     */
    public function test_a_truncated_result_admits_it_is_truncated(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->pinjaman([
                'loan_number' => sprintf('PINJ-BANYAK-%03d', $i),
                'status' => 'dicairkan',
                'disbursed_at' => '2026-08-20',
            ]);
        }

        $this->layar()
            ->assertSee('dari 30 pinjaman')
            ->assertSee('hal=2');
    }

    public function test_the_second_page_keeps_the_active_filters(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->pinjaman([
                'loan_number' => sprintf('PINJ-SARING-%03d', $i),
                'status' => 'dicairkan',
                'disbursed_at' => '2026-08-20',
            ]);
        }

        $this->layar(['cari' => 'SARING'])->assertSee('cari=SARING');
    }

    /** Layar tanpa saringan harus tetap seperti semula. */
    public function test_an_unfiltered_screen_still_shows_the_queue(): void
    {
        $this->pinjaman(['loan_number' => 'PINJ-BIASA-001']);

        $this->layar()->assertSee('PINJ-BIASA-001');
    }

    public function test_an_empty_result_says_it_is_the_filter_not_an_empty_queue(): void
    {
        $this->pinjaman(['loan_number' => 'PINJ-ADA-001']);

        $this->layar(['cari' => 'tidak-akan-ketemu'])
            ->assertSee('cocok dengan saringan ini')
            ->assertDontSee('PINJ-ADA-001');
    }
}

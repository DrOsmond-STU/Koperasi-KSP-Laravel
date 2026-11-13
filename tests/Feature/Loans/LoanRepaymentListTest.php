<?php

namespace Tests\Feature\Loans;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar Angsuran Anggota — seluruh angsuran yang sudah dicatat, dengan
 * pencarian, filter periode (tanggal bayar), filter anggota, pagination, dan
 * cetak PDF yang memakai filter yang sama.
 */
class LoanRepaymentListTest extends TestCase
{
    use RefreshDatabase;

    private function staf(string $role = 'petugas_kredit'): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $user->assignRole($role);
        UserBranchScope::query()->create(['user_id' => $user->id, 'scope_type' => 'all']);

        return $user;
    }

    private function angsuran(Loan $loan, string $paidAt, float $pokok, float $jasa, array $extra = []): LoanRepayment
    {
        return LoanRepayment::query()->create(array_merge([
            'branch_id' => $loan->branch_id,
            'loan_id' => $loan->id,
            'amount' => $pokok + $jasa,
            'principal_portion' => $pokok,
            'interest_portion' => $jasa,
            'penalty_portion' => 0,
            'balance_after' => 0,
            'paid_at' => $paidAt,
            'transaction_date' => $paidAt,
            'created_by' => User::query()->value('id'),
        ], $extra));
    }

    public function test_lists_filters_by_period_member_and_search_and_totals_skip_cancelled(): void
    {
        $user = $this->staf();
        $budi = Loan::factory()->create(['status' => 'dicairkan']);
        $budi->member->update(['name' => 'Budi Angsur']);
        $sari = Loan::factory()->create(['status' => 'dicairkan']);
        $sari->member->update(['name' => 'Sari Lunas']);

        $this->angsuran($budi, '2026-07-05', 1000000, 100000);
        $this->angsuran($budi, '2026-08-05', 1000000, 100000);
        $this->angsuran($budi, '2026-08-06', 500000, 50000, ['cancelled_at' => now(), 'cancellation_reason' => 'salah catat']);
        $this->angsuran($sari, '2026-08-10', 2000000, 200000);

        $semua = $this->actingAs($user)->get(route('staf.angsuran.daftar'));
        $semua->assertOk()->assertSee('Budi Angsur')->assertSee('Sari Lunas')->assertSee('BATAL');
        $this->assertSame(4, $semua->viewData('repayments')->total());
        $this->assertSame(3, $semua->viewData('totals')['jumlah']);
        $this->assertSame(1, $semua->viewData('totals')['jumlah_batal']);
        $this->assertEqualsWithDelta(4400000, $semua->viewData('totals')['total'], 0.001);

        // Periode memakai tanggal bayar, bukan tanggal input.
        $agustus = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']));
        $this->assertSame(3, $agustus->viewData('repayments')->total());
        $this->assertEqualsWithDelta(3300000, $agustus->viewData('totals')['total'], 0.001);

        $perAnggota = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['member_id' => $budi->member_id, 'status' => 'aktif']));
        $this->assertSame(2, $perAnggota->viewData('repayments')->total());
        $this->assertEqualsWithDelta(2000000, $perAnggota->viewData('totals')['pokok'], 0.001);

        $cari = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['q' => 'Sari']));
        $this->assertSame(1, $cari->viewData('repayments')->total());
        $this->assertSame('Sari Lunas', $cari->viewData('repayments')->first()->loan->member->name);

        $noPinjaman = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['q' => $sari->loan_number]));
        $this->assertSame(1, $noPinjaman->viewData('repayments')->total());
    }

    public function test_is_paginated_on_the_server_and_carries_filters_to_other_pages(): void
    {
        $user = $this->staf();
        $loan = Loan::factory()->create(['status' => 'dicairkan']);
        foreach (range(1, 30) as $i) {
            $this->angsuran($loan, '2026-08-'.sprintf('%02d', min($i, 28)), 100000, 10000);
        }

        $halaman1 = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['date_from' => '2026-08-01']));
        $this->assertCount(25, $halaman1->viewData('repayments')->items());
        $this->assertStringContainsString('date_from=2026-08-01', $halaman1->viewData('repayments')->nextPageUrl());
        // Rekap tetap menjumlah seluruh hasil filter, bukan hanya halaman ini.
        $this->assertSame(30, $halaman1->viewData('totals')['jumlah']);

        $halaman2 = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['date_from' => '2026-08-01', 'page' => 2]));
        $this->assertCount(5, $halaman2->viewData('repayments')->items());

        $kecil = $this->actingAs($user)->get(route('staf.angsuran.daftar', ['per_page' => 10]));
        $this->assertSame(3, $kecil->viewData('repayments')->lastPage());
    }

    public function test_print_downloads_a_pdf_for_the_filtered_data(): void
    {
        $user = $this->staf('manajer');
        $loan = Loan::factory()->create(['status' => 'dicairkan']);
        $this->angsuran($loan, '2026-08-05', 1000000, 100000);

        $response = $this->actingAs($user)->get(route('staf.angsuran.daftar.cetak', [
            'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'member_id' => $loan->member_id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('daftar-angsuran-anggota-2026-08-01-sd-2026-08-31.pdf', $response->headers->get('content-disposition'));
    }

    public function test_member_only_accounts_and_roles_without_pinjaman_read_are_refused(): void
    {
        $anggota = $this->staf('anggota');
        $this->actingAs($anggota)->get(route('staf.angsuran.daftar'))->assertForbidden();
        $this->actingAs($anggota)->get(route('staf.angsuran.daftar.cetak'))->assertForbidden();

        $bendahara = $this->staf('bendahara');
        $this->actingAs($bendahara)->get(route('staf.angsuran.daftar'))->assertForbidden();
    }

    public function test_menu_appears_in_the_sidebar_for_staff(): void
    {
        $user = $this->staf();

        $this->actingAs($user)->get(route('staf.angsuran.daftar'))
            ->assertSee('Daftar Angsuran Anggota')
            ->assertSee(route('staf.angsuran.daftar'), false);
    }
}

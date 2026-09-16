<?php

namespace Tests\Feature\Prints;

use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\User;
use App\Models\UserBranchScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Surat pengajuan yang dibatalkan harus menceritakan pembatalannya.
 *
 * Keluhan nyata 16 Sep 2026 atas 51-100H-260813-9703: suratnya menulis
 * "Status: Dibatalkan" satu kata, lalu menyusul tabel berisi dua baris
 * "Setuju" — terbaca seolah pinjamannya masih disetujui. Persetujuan itu
 * memang benar terjadi dan tidak boleh dihapus (catatan audit); yang
 * hilang justru keterangan bahwa pembatalan datang sesudahnya.
 *
 * Yang dikunci di sini bukan tata letaknya, melainkan bahwa keempat fakta
 * pembatalan — kapan, oleh siapa, alasannya, dan nasib dananya — benar
 * benar muncul di kertas.
 */
class CancelledLoanApplicationPrintTest extends TestCase
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

    /**
     * Blade-nya dirender langsung, bukan lewat PDF-nya: yang diuji di sini
     * kalimat di atas kertas, dan mengorek teks dari PDF biner hanya
     * menambah cara gagal yang tidak ada hubungannya dengan itu. Bahwa
     * rutenya sendiri masih hidup diuji terpisah di bawah.
     */
    private function suratUntuk(Loan $loan): string
    {
        return view('prints.loans.application', [
            'loan' => $loan->load('member', 'loanProduct', 'createdBy', 'cancelledBy', 'approvals.approvedBy'),
            'generatedAt' => now(),
        ])->render();
    }

    private function pinjamanDibatalkan(array $ubah = []): Loan
    {
        $pembatal = User::factory()->create(['name' => 'Rahmi Pembatal']);
        $penyetuju = User::factory()->create(['name' => 'Sudirja Penyetuju']);

        $loan = Loan::factory()->create(array_merge([
            'created_by' => User::factory()->create()->id,
            'principal_amount' => 30_000_000,
            'status' => 'dibatalkan',
            'submitted_at' => '2026-08-13',
            'cancelled_at' => '2026-09-16 13:03:53',
            'cancelled_by' => $pembatal->id,
            'cancellation_reason' => 'Salah tanggal pencairan',
        ], $ubah));

        LoanApproval::query()->create([
            'loan_id' => $loan->id,
            'approved_by' => $penyetuju->id,
            'decision' => 'setuju',
            'decided_at' => '2026-08-13 00:00:00',
        ]);

        return $loan->fresh();
    }

    public function test_the_letter_states_who_cancelled_it_and_why(): void
    {
        $surat = $this->suratUntuk($this->pinjamanDibatalkan());

        $this->assertStringContainsString('DIBATALKAN', $surat);
        $this->assertStringContainsString('Rahmi Pembatal', $surat);
        $this->assertStringContainsString('Salah tanggal pencairan', $surat);
    }

    /** Persetujuan lama tetap tercetak — jejak audit, bukan aib yang disembunyikan. */
    public function test_earlier_approvals_are_still_printed(): void
    {
        $surat = $this->suratUntuk($this->pinjamanDibatalkan());

        $this->assertStringContainsString('Sudirja Penyetuju', $surat);
        $this->assertStringContainsString('Setuju', $surat);
    }

    /** ...tapi tidak boleh lagi terbaca sebagai persetujuan yang masih hidup. */
    public function test_those_approvals_are_marked_as_no_longer_valid(): void
    {
        $surat = $this->suratUntuk($this->pinjamanDibatalkan());

        $this->assertStringContainsString('tidak berlaku', $surat);
    }

    /** Pinjaman yang sempat cair: pembacanya harus tahu dananya sudah dibalik. */
    public function test_a_reversed_disbursement_is_spelled_out(): void
    {
        $surat = $this->suratUntuk($this->pinjamanDibatalkan([
            'disbursed_at' => '2026-09-16',
        ]));

        $this->assertStringContainsString('Sempat dicairkan', $surat);
        $this->assertStringContainsString('dibalik dengan jurnal koreksi', $surat);
    }

    /** Pengajuan yang ditarik sebelum cair: jangan sampai mengaku pernah ada uang keluar. */
    public function test_an_application_never_disbursed_says_so(): void
    {
        $surat = $this->suratUntuk($this->pinjamanDibatalkan(['disbursed_at' => null]));

        $this->assertStringContainsString('Tidak pernah dicairkan', $surat);
        $this->assertStringNotContainsString('Sempat dicairkan', $surat);
    }

    /** Pinjaman yang sehat tidak boleh tiba-tiba memuat blok pembatalan. */
    public function test_a_live_loan_carries_no_cancellation_block(): void
    {
        $loan = Loan::factory()->create([
            'created_by' => User::factory()->create()->id,
            'status' => 'dicairkan',
            'submitted_at' => '2026-08-13',
            'disbursed_at' => '2026-08-13',
            'cancelled_at' => null,
        ]);

        $surat = $this->suratUntuk($loan);

        $this->assertStringNotContainsString('DIBATALKAN', $surat);
        $this->assertStringNotContainsString('tidak berlaku', $surat);
    }

    /**
     * Blok pembatalan memanggil relasi cancelledBy yang baru. Kalau relasi
     * itu hilang atau controller-nya lupa memuatnya, Blade tetap lolos
     * dirender langsung tapi rutenya balas 500 — jadi rute aslinya diuji
     * juga, sekali, pada pinjaman yang memang dibatalkan.
     */
    public function test_the_real_print_route_still_returns_the_pdf(): void
    {
        $loan = $this->pinjamanDibatalkan(['disbursed_at' => '2026-09-16']);

        $this->actingAs($this->pengurus())
            ->get(route('admin.print.loan-application.show', $loan))
            ->assertOk();
    }
}

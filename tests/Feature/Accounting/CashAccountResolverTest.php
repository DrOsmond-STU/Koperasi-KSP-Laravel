<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\CashAccountException;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\CashAccountResolver;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu-satunya penentu akun kas lawan transaksi. Sebelumnya sepuluh service
 * memutuskannya sendiri-sendiri lewat konstanta '1101' masing-masing, dan
 * koperasi yang memakai bagan akun sendiri menjadikan 1101 akun header —
 * sehingga setiap alur yang jatuh ke konstanta itu ditolak JournalEngine.
 */
class CashAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function resolver(): CashAccountResolver
    {
        return app(CashAccountResolver::class);
    }

    private function kas(string $code, string $name, bool $postable = true): ChartOfAccount
    {
        return ChartOfAccount::factory()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'ASET',
            'normal_balance' => 'DEBIT',
            'statement' => 'NERACA',
            'is_postable' => $postable,
        ]);
    }

    public function test_branch_cash_account_wins_over_the_default(): void
    {
        $kas = $this->kas('1101500', 'KAS AO ASMAWI KSP');
        $branch = Branch::factory()->create(['cash_account_id' => $kas->id]);

        $this->assertEquals($kas->id, $this->resolver()->forBranch($branch->id)->id);
    }

    public function test_explicit_override_wins_over_the_branch_account(): void
    {
        $kasCabang = $this->kas('1101500', 'KAS AO ASMAWI KSP');
        $dipilih = $this->kas('1101100', 'KAS KECIL (KSP)');
        $branch = Branch::factory()->create(['cash_account_id' => $kasCabang->id]);

        $this->assertEquals($dipilih->id, $this->resolver()->forBranch($branch->id, $dipilih)->id);
    }

    public function test_branch_without_cash_account_falls_back_to_the_configured_default(): void
    {
        $branch = Branch::factory()->create(['cash_account_id' => null]);
        $bawaan = ChartOfAccount::query()->where('code', '1101')->firstOrFail();

        $this->assertEquals($bawaan->id, $this->resolver()->forBranch($branch->id)->id);
    }

    /** Persis keadaan produksi: cabang belum diatur DAN akun bawaan jadi header. */
    public function test_non_postable_default_is_rejected_with_an_actionable_message(): void
    {
        $branch = Branch::factory()->create(['cash_account_id' => null]);
        ChartOfAccount::query()->where('code', '1101')->update(['is_postable' => false]);

        $this->expectException(CashAccountException::class);
        $this->expectExceptionMessageMatches('/akun header.*Kas Cabang/s');

        $this->resolver()->forBranch($branch->id);
    }

    /** Akun kas cabang yang sendirinya akun header juga harus ditolak di sini. */
    public function test_non_postable_branch_account_is_rejected(): void
    {
        $kas = $this->kas('1101500', 'KAS AO ASMAWI KSP', postable: false);
        $branch = Branch::factory()->create(['cash_account_id' => $kas->id]);

        $this->expectException(CashAccountException::class);
        $this->expectExceptionMessageMatches('/1101500/');

        $this->resolver()->forBranch($branch->id);
    }

    /**
     * Dengan config dikosongkan, jaring pengaman mati: yang muncul justru
     * kegagalan aslinya, yang menyebut cabang mana yang belum diatur.
     */
    public function test_empty_default_config_surfaces_the_branch_that_needs_configuring(): void
    {
        config(['koperasi.akun_kas_bawaan' => '']);
        $branch = Branch::factory()->create(['cash_account_id' => null, 'name' => 'Unit Tanpa Kas']);

        $this->expectException(CashAccountException::class);
        $this->expectExceptionMessageMatches('/Unit Tanpa Kas/');

        $this->resolver()->forBranch($branch->id);
    }

    public function test_resolution_by_branch_code_follows_the_live_branch_mapping(): void
    {
        $kas = $this->kas('1101600', 'KAS AO JAYA BUDIMAN (UPF)');
        Branch::factory()->create(['code' => '003', 'cash_account_id' => $kas->id]);

        $this->assertEquals($kas->id, $this->resolver()->forBranchCode('003')->id);
    }

    /** Tampilan tidak boleh ikut gagal hanya karena akun kasnya belum diatur. */
    public function test_try_variants_return_null_instead_of_throwing(): void
    {
        config(['koperasi.akun_kas_bawaan' => '']);

        $this->assertNull($this->resolver()->tryForBranchCode('tidak-ada'));
        $this->assertNull($this->resolver()->tryForBranch(null));
    }
}

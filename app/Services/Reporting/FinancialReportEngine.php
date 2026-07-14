<?php

namespace App\Services\Reporting;

use App\Models\ChartOfAccount;
use App\Models\JournalLine;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Mesin Laporan Keuangan on-demand dari ledger (PRD §2.3/§17, Task 5.3) —
 * Neraca, Laba Rugi, Arus Kas, CALK. Every figure is computed fresh from
 * journal_lines every call (LED-09 single source of truth) — nothing is
 * cached or precomputed, same principle as GeneralLedgerService.
 *
 * `$basis` ('sak_ep'|'sak_emkm') only changes report titles/labels — the
 * underlying chart of accounts and ledger are shared across both bases
 * (SEED_CHART_OF_ACCOUNTS.md: "kompatibel untuk keduanya"), so the figures
 * are always identical regardless of basis (RPT-04).
 */
class FinancialReportEngine
{
    private const RAK_ACCOUNT_CODE = '1132';

    private const CASH_BANK_CODES = ['1101', '1102', '1110'];

    public function __construct(private readonly GeneralLedgerService $ledger) {}

    /**
     * Neraca (Balance Sheet) — RPT-01/RPT-05.
     */
    public function neraca(?int $branchId, string $asOfDate, string $basis = 'sak_ep'): array
    {
        $accounts = ChartOfAccount::query()
            ->where('statement', 'NERACA')
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($branchId, $asOfDate) {
            $isRak = $account->code === self::RAK_ACCOUNT_CODE;
            $eliminated = $branchId === null && $isRak;

            return [
                'account' => $account,
                'balance' => $eliminated ? '0.00' : $this->ledger->balanceFor($account, $branchId, $asOfDate),
                'eliminated' => $eliminated,
            ];
        });

        $totalAset = $this->sumByType($rows, 'ASET');
        $totalLiabilitas = $this->sumByType($rows, 'LIABILITAS');
        $totalEkuitas = $this->sumByType($rows, 'EKUITAS');

        return [
            'basis' => $basis,
            'as_of_date' => $asOfDate,
            'is_consolidated' => $branchId === null,
            'rows' => $rows,
            'total_aset' => $totalAset,
            'total_liabilitas' => $totalLiabilitas,
            'total_ekuitas' => $totalEkuitas,
            'is_balanced' => bccomp($totalAset, bcadd($totalLiabilitas, $totalEkuitas, 2), 2) === 0,
            'has_data' => bccomp($totalAset, '0', 2) !== 0 || bccomp($totalLiabilitas, '0', 2) !== 0,
        ];
    }

    /**
     * Laba Rugi / Perhitungan Hasil Usaha — RPT-02.
     */
    public function labaRugi(?int $branchId, string $periodStart, string $periodEnd, string $basis = 'sak_ep'): array
    {
        $accounts = ChartOfAccount::query()
            ->where('statement', 'LABA_RUGI')
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(fn (ChartOfAccount $account) => [
            'account' => $account,
            'amount' => $this->periodMovement($account, $branchId, $periodStart, $periodEnd),
        ]);

        $totalPendapatan = $this->sumByType($rows, 'PENDAPATAN', 'amount');
        $totalBeban = $this->sumByType($rows, 'BEBAN', 'amount');
        $shu = bcsub($totalPendapatan, $totalBeban, 2);

        return [
            'basis' => $basis,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_consolidated' => $branchId === null,
            'rows' => $rows,
            'total_pendapatan' => $totalPendapatan,
            'total_beban' => $totalBeban,
            'shu' => $shu,
            'has_data' => $rows->contains(fn (array $row) => bccomp($row['amount'], '0', 2) !== 0),
        ];
    }

    /**
     * Arus Kas — RPT-03. Direct-method summary (masuk/keluar/bersih)
     * reconciled against opening/closing ledger balances of the cash/bank
     * accounts (LED-09 style consistency check).
     */
    public function arusKas(?int $branchId, string $periodStart, string $periodEnd): array
    {
        $cashAccounts = ChartOfAccount::query()->whereIn('code', self::CASH_BANK_CODES)->get();
        $dayBeforeStart = Carbon::parse($periodStart)->subDay()->toDateString();

        $opening = '0.00';
        $closing = '0.00';
        $totalMasuk = '0.00';
        $totalKeluar = '0.00';

        foreach ($cashAccounts as $account) {
            $opening = bcadd($opening, $this->ledger->balanceFor($account, $branchId, $dayBeforeStart), 2);
            $closing = bcadd($closing, $this->ledger->balanceFor($account, $branchId, $periodEnd), 2);

            $sums = JournalLine::query()
                ->where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($query) use ($branchId, $periodStart, $periodEnd) {
                    $query->whereBetween('entry_date', [$periodStart, $periodEnd]);

                    if ($branchId !== null) {
                        $query->where('branch_id', $branchId);
                    }
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $totalMasuk = bcadd($totalMasuk, (string) $sums->total_debit, 2);
            $totalKeluar = bcadd($totalKeluar, (string) $sums->total_credit, 2);
        }

        $netMovement = bcsub($totalMasuk, $totalKeluar, 2);

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_consolidated' => $branchId === null,
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'net_movement' => $netMovement,
            'is_consistent' => bccomp(bcadd($opening, $netMovement, 2), $closing, 2) === 0,
            'has_data' => bccomp($totalMasuk, '0', 2) !== 0 || bccomp($totalKeluar, '0', 2) !== 0,
        ];
    }

    /**
     * CALK (Catatan atas Laporan Keuangan) — a structured summary of
     * balances per akun group plus the standard accounting-policy notes,
     * not a full free-text disclosure document.
     */
    public function calk(?int $branchId, string $asOfDate, string $basis = 'sak_ep'): array
    {
        $accounts = ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get();

        $groups = $accounts
            ->map(fn (ChartOfAccount $account) => [
                'account' => $account,
                'balance' => $this->ledger->balanceFor($account, $branchId, $asOfDate),
            ])
            ->groupBy(fn (array $row) => $row['account']->group);

        return [
            'basis' => $basis,
            'as_of_date' => $asOfDate,
            'is_consolidated' => $branchId === null,
            'kebijakan_akuntansi' => $this->standardAccountingPolicies($basis),
            'groups' => $groups,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function standardAccountingPolicies(string $basis): array
    {
        $standardName = $basis === 'sak_emkm' ? 'SAK EMKM' : 'SAK EP';

        return [
            "Laporan keuangan disusun berdasarkan {$standardName} dan Permenkop UKM No. 2/2024.",
            'Dasar pengukuran adalah biaya historis, kecuali dinyatakan lain.',
            'Pengakuan pendapatan & beban menggunakan basis akrual.',
            'Penyusutan aktiva tetap dihitung sesuai metode yang ditetapkan per aset (Garis Lurus/Saldo Menurun).',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function sumByType(Collection $rows, string $type, string $valueKey = 'balance'): string
    {
        return $rows
            ->filter(fn (array $row) => $row['account']->type === $type)
            ->reduce(fn (string $carry, array $row) => bcadd($carry, $row[$valueKey], 2), '0.00');
    }

    private function periodMovement(ChartOfAccount $account, ?int $branchId, string $periodStart, string $periodEnd): string
    {
        $isDebitNormal = $account->normal_balance === 'DEBIT';

        $sums = JournalLine::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($query) use ($branchId, $periodStart, $periodEnd) {
                $query->whereBetween('entry_date', [$periodStart, $periodEnd]);

                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        return $isDebitNormal
            ? bcsub((string) $sums->total_debit, (string) $sums->total_credit, 2)
            : bcsub((string) $sums->total_credit, (string) $sums->total_debit, 2);
    }
}

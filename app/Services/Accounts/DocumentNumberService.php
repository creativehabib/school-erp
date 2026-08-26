<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Models\Accounts\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free document numbering under concurrency.
 *
 * The naive `max(invoice_no) + 1` approach breaks the moment two cashiers
 * collect a fee in the same second: both read the same max and both write the
 * same receipt number, which the unique index then rejects (or worse, doesn't).
 * Here we take a row-level lock on the counter, so the second request simply
 * waits a few milliseconds and gets the next value.
 *
 * Must be called inside a surrounding transaction when the number is being
 * used for a record created in that same transaction.
 */
class DocumentNumberService
{
    public const INVOICE = 'invoice';
    public const FEE_RECEIPT = 'fee_receipt';
    public const EXPENSE_VOUCHER = 'expense_voucher';
    public const PAYSLIP = 'payslip';
    public const ADMISSION = 'admission';

    public function next(
        string $key,
        ?string $scope = null,
        ?string $prefix = null,
        int $padding = 5
    ): string {
        $scope ??= 'global';

        return DB::transaction(function () use ($key, $scope, $prefix, $padding) {
            DocumentSequence::query()->firstOrCreate(
                ['key' => $key, 'scope' => $scope],
                ['prefix' => $prefix, 'padding' => $padding, 'next_number' => 1],
            );

            /** @var DocumentSequence $sequence */
            $sequence = DocumentSequence::query()
                ->where('key', $key)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $sequence->next_number;

            $sequence->forceFill(['next_number' => $number + 1])->save();

            return $sequence->format($number);
        });
    }

    /** INV-2026-08-00001 */
    public function invoiceNumber(int $year, int $month): string
    {
        $scope = sprintf('%d-%02d', $year, $month);

        return $this->next(self::INVOICE, $scope, "INV-{$scope}-", 5);
    }

    /** RCP-2026-000123 */
    public function receiptNumber(int $year): string
    {
        return $this->next(self::FEE_RECEIPT, (string) $year, "RCP-{$year}-", 6);
    }

    /** EXP-2026-000123 */
    public function expenseVoucherNumber(int $year): string
    {
        return $this->next(self::EXPENSE_VOUCHER, (string) $year, "EXP-{$year}-", 6);
    }

    /** PS-2026-08-0001 */
    public function payslipNumber(int $year, int $month): string
    {
        $scope = sprintf('%d-%02d', $year, $month);

        return $this->next(self::PAYSLIP, $scope, "PS-{$scope}-", 4);
    }
}

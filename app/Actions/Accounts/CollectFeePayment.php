<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionDirection;
use App\Models\Academic\Student;
use App\Models\Accounts\Invoice;
use App\Models\Accounts\Payment;
use App\Models\Accounts\PaymentAllocation;
use App\Services\Accounts\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collect money from a guardian and settle it against outstanding invoices.
 *
 * Default behaviour is oldest-invoice-first (FIFO), which is what a school
 * accountant expects and what keeps the arrears report sane. Anything left over
 * after all dues are cleared stays on the Payment as an unallocated advance.
 *
 * Everything happens in ONE transaction, and every invoice touched is locked
 * before it is read, so two cashiers collecting for the same student cannot
 * both allocate against the same due amount.
 */
class CollectFeePayment
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly RecordCashBookEntry $cashBook,
    ) {}

    /**
     * @param  array<int, int>|null  $invoiceIds  Explicit targets; null = FIFO.
     */
    public function handle(
        Student $student,
        float $amount,
        PaymentMethod $method,
        ?int $financialAccountId = null,
        ?array $invoiceIds = null,
        ?string $reference = null,
        ?\DateTimeInterface $paidAt = null,
        ?int $guardianId = null,
        ?string $note = null,
    ): Payment {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $paidAt ??= now();

        return DB::transaction(function () use (
            $student, $amount, $method, $financialAccountId,
            $invoiceIds, $reference, $paidAt, $guardianId, $note
        ) {
            $payment = Payment::create([
                'voucher_no' => $this->numbers->receiptNumber((int) $paidAt->format('Y')),
                'student_id' => $student->id,
                'guardian_id' => $guardianId,
                'amount' => $amount,
                'allocated_amount' => 0,
                'method' => $method,
                'financial_account_id' => $financialAccountId,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'status' => PaymentStatus::Completed,
                'received_by' => auth()->id(),
                'note' => $note,
            ]);

            $remaining = round($amount, 2);

            foreach ($this->targetInvoices($student, $invoiceIds) as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $due = (float) $invoice->due_total;

                if ($due <= 0) {
                    continue;
                }

                $applied = min($due, $remaining);

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $applied,
                ]);

                $invoice->recalculate();

                $remaining = round($remaining - $applied, 2);
            }

            $payment->forceFill([
                'allocated_amount' => round($amount - $remaining, 2),
            ])->save();

            $this->cashBook->handle(
                source: $payment,
                direction: TransactionDirection::In,
                amount: $amount,
                date: $paidAt,
                financialAccountId: $financialAccountId,
                category: 'fee_collection',
                description: "Fee received from {$student->name_en} ({$student->admission_no})",
            );

            return $payment->load('allocations.invoice');
        });
    }

    /**
     * @param  array<int, int>|null  $invoiceIds
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    private function targetInvoices(Student $student, ?array $invoiceIds): \Illuminate\Support\Collection
    {
        return Invoice::query()
            ->where('student_id', $student->id)
            ->outstanding()
            ->when($invoiceIds, fn ($q) => $q->whereIn('id', $invoiceIds))
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}

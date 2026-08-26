<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Models\Accounts\Payment;
use Illuminate\Database\Eloquent\Model;

/**
 * Fee receipt for one payment.
 *
 * The receipt itemises WHICH invoices the money was applied to, not just the total. A
 * parent who pays 4,500 taka against three months of arrears needs to see which months
 * are now clear, and the office needs the same breakdown when the parent returns in
 * October disputing September.
 *
 * Amount in words is included because a Bangladeshi receipt is expected to carry it, and
 * because it is the line that stops a "500" from being altered to a "5000" in ink.
 */
final class FeeReceiptPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Payment::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var Payment $payment */
        $payment = $subject;

        $payment->loadMissing([
            'student.currentEnrollment.schoolClass',
            'student.currentEnrollment.section',
            'guardian',
            'receiver',
            'financialAccount',
            'allocations.invoice',
        ]);

        $student = $payment->student;
        $enrollment = $student?->currentEnrollment;

        $items = $payment->allocations->map(fn ($allocation) => [
            'invoice_no' => $allocation->invoice?->invoice_no,
            'period' => $this->invoicePeriod($allocation->invoice),
            'invoice_total' => $this->money($allocation->invoice?->grand_total ?? 0),
            'applied' => $this->money($allocation->amount),
            'remaining_due' => $this->money($allocation->invoice?->due_total ?? 0),
        ])->values()->all();

        return [
            'school' => $this->school(),
            'receipt' => [
                'voucher_no' => $payment->voucher_no,
                'paid_at' => $this->date($payment->paid_at),
                'paid_at_time' => $this->date($payment->paid_at, 'd/m/Y h:i A'),
                'method' => $payment->method instanceof \App\Enums\PaymentMethod
                    ? $payment->method->label()
                    : (string) $payment->method,
                'reference' => $payment->reference,
                'account' => $payment->financialAccount?->name,
                'amount' => $this->money($payment->amount),
                'amount_in_words' => $this->amountInWords((float) $payment->amount),
                'allocated' => $this->money($payment->allocated_amount),
                'unallocated' => $this->money($payment->unallocatedAmount()),
                'status' => $payment->status instanceof \App\Enums\PaymentStatus
                    ? $payment->status->label()
                    : (string) $payment->status,
                'note' => $payment->note,
                'received_by' => $payment->receiver?->name,
            ],
            'student' => [
                'name_en' => $student?->name_en,
                'name_bn' => $student?->name_bn,
                'admission_no' => $student?->admission_no,
                'father_name' => $student?->father_name,
                'class' => $enrollment?->schoolClass?->name,
                'section' => $enrollment?->section?->name,
                'class_roll' => $enrollment?->class_roll,
                'session' => $enrollment?->academicSession?->name,
            ],
            'payer' => [
                'name' => $payment->guardian?->name_en ?? $student?->father_name,
                'relation' => $payment->guardian?->relation,
                'phone' => $payment->guardian?->phone ?? $student?->phone,
            ],
            'items' => $items,
            'item_count' => count($items),
            'balance_after' => $student !== null ? $this->money($student->totalDue()) : '0.00',
            // Two copies on one sheet: one for the parent, one for the office file. This
            // is how every fee counter in the country actually works, and printing a
            // single copy just means the clerk photocopies it.
            'copies' => $context['copies'] ?? ["Parent's Copy", "Office Copy"],
            'qr' => $student !== null ? $this->qrFor($student, 'fee_receipt') : ['token' => null, 'url' => null, 'src' => null],
            'barcode' => $this->barcodeFor($payment->voucher_no),
            'issued' => $this->issuance($payment->paid_at),
        ];
    }

    private function invoicePeriod(?Model $invoice): ?string
    {
        if ($invoice === null) {
            return null;
        }

        if (blank($invoice->billing_month) || blank($invoice->billing_year)) {
            return null;
        }

        return \Illuminate\Support\Carbon::create(
            (int) $invoice->billing_year,
            (int) $invoice->billing_month,
            1,
        )?->format('F Y');
    }
}

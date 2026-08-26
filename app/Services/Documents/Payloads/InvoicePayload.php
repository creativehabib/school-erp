<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Models\Accounts\Invoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Fee invoice / bill.
 *
 * Line items come from invoice_items, which were snapshotted when the invoice was raised.
 * That is deliberate and it matters here more than anywhere else in the module: fee
 * structures get revised mid-year, and an invoice must keep showing the tuition figure
 * the parent was actually billed, not the figure the school charges today. Recomputing
 * from the live fee structure would retroactively change bills people have already paid.
 *
 * Payment instructions are carried in the payload so the same template serves a school
 * collecting cash at a counter and one collecting through bKash.
 */
final class InvoicePayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Invoice::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var Invoice $invoice */
        $invoice = $subject;

        $invoice->loadMissing([
            'student', 'academicSession', 'schoolClass', 'section',
            'items.feeHead', 'allocations.payment', 'creator',
        ]);

        $student = $invoice->student;
        $guardian = $student?->primaryGuardian();

        $items = $invoice->items
            ->sortBy('sort_order')
            ->map(fn ($item) => [
                'name' => $item->name,
                'head' => $item->feeHead?->name,
                'amount' => $this->money($item->amount),
                'discount' => $this->money($item->discount),
                'fine' => $this->money($item->fine),
                'total' => $this->money($item->total),
                'has_discount' => (float) $item->discount > 0,
                'has_fine' => (float) $item->fine > 0,
            ])
            ->values()
            ->all();

        $payments = $invoice->allocations
            ->map(fn ($allocation) => [
                'voucher_no' => $allocation->payment?->voucher_no,
                'paid_at' => $this->date($allocation->payment?->paid_at),
                'amount' => $this->money($allocation->amount),
                'method' => $allocation->payment?->method instanceof \App\Enums\PaymentMethod
                    ? $allocation->payment->method->label()
                    : (string) ($allocation->payment?->method ?? ''),
            ])
            ->values()
            ->all();

        return [
            'school' => $this->school(),
            'invoice' => [
                'invoice_no' => $invoice->invoice_no,
                'issue_date' => $this->date($invoice->issue_date),
                'due_date' => $this->date($invoice->due_date),
                'period' => $this->period($invoice),
                'session' => $invoice->academicSession?->name,
                'subtotal' => $this->money($invoice->subtotal),
                'discount_total' => $this->money($invoice->discount_total),
                'fine_total' => $this->money($invoice->fine_total),
                'grand_total' => $this->money($invoice->grand_total),
                'paid_total' => $this->money($invoice->paid_total),
                'due_total' => $this->money($invoice->due_total),
                'grand_total_words' => $this->amountInWords((float) $invoice->grand_total),
                'status' => $invoice->status instanceof \App\Enums\InvoiceStatus
                    ? $invoice->status->label()
                    : (string) $invoice->status,
                'is_overdue' => $invoice->daysOverdue() > 0,
                'days_overdue' => $invoice->daysOverdue(),
                'note' => $invoice->note,
                'created_by' => $invoice->creator?->name,
            ],
            'student' => [
                'name_en' => $student?->name_en,
                'name_bn' => $student?->name_bn,
                'admission_no' => $student?->admission_no,
                'father_name' => $student?->father_name,
                'class' => $invoice->schoolClass?->name,
                'section' => $invoice->section?->name,
                'class_roll' => $student?->currentEnrollment?->class_roll,
            ],
            'guardian' => [
                'name' => $guardian?->name_en,
                'phone' => $guardian?->phone,
            ],
            'items' => $items,
            'payments' => $payments,
            'has_payments' => $payments !== [],
            'payment_instructions' => $context['payment_instructions']
                ?? config('school.payment_instructions', []),
            'qr' => $student !== null ? $this->qrFor($student, 'invoice') : ['token' => null, 'url' => null, 'src' => null],
            'barcode' => $this->barcodeFor($invoice->invoice_no),
            'issued' => $this->issuance($invoice->issue_date),
        ];
    }

    private function period(Invoice $invoice): ?string
    {
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

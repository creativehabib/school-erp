<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\FeeFrequency;
use App\Enums\InvoiceStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\StudentEnrollment;
use App\Models\Accounts\FeeStructure;
use App\Models\Accounts\FeeWaiver;
use App\Models\Accounts\Invoice;
use App\Models\Accounts\InvoiceItem;
use App\Services\Accounts\DocumentNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-bill a month.
 *
 * Two properties matter more than speed here:
 *
 *  1. IDEMPOTENT — re-running for the same month skips students who already
 *     have an invoice (unique-ish guard on student + billing period). An
 *     accountant will click this twice; the second click must be harmless.
 *  2. CHUNKED — a 2,000-student school must not load every enrollment into
 *     memory, so we stream enrollments and commit per student.
 */
class GenerateMonthlyInvoices
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @return array{created: int, skipped: int}
     */
    public function handle(
        AcademicSession $session,
        int $year,
        int $month,
        ?int $schoolClassId = null,
    ): array {
        $issueDate = CarbonImmutable::create($year, $month, 1);
        $created = 0;
        $skipped = 0;

        // Rate card cached once per class/shift pair — avoids an N+1 across
        // hundreds of students who all share the same fees.
        $rateCache = [];

        StudentEnrollment::query()
            ->with('student')
            ->forSession($session->id)
            ->current()
            ->when($schoolClassId, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->chunkById(200, function (Collection $enrollments) use (
                &$created, &$skipped, &$rateCache, $session, $year, $month, $issueDate
            ) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->student) {
                        $skipped++;

                        continue;
                    }

                    $exists = Invoice::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('billing_year', $year)
                        ->where('billing_month', $month)
                        ->whereNot('status', InvoiceStatus::Cancelled)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $cacheKey = "{$enrollment->school_class_id}:{$enrollment->shift_id}";

                    $rateCache[$cacheKey] ??= FeeStructure::query()
                        ->with('feeHead')
                        ->for($session->id, $enrollment->school_class_id, $enrollment->shift_id)
                        ->get()
                        ->filter(fn (FeeStructure $s) => $s->feeHead?->frequency === FeeFrequency::Monthly)
                        // Shift-specific row wins over the NULL fallback.
                        ->unique(fn (FeeStructure $s) => $s->fee_head_id);

                    $rates = $rateCache[$cacheKey];

                    if ($rates->isEmpty()) {
                        $skipped++;

                        continue;
                    }

                    $waivers = FeeWaiver::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('academic_session_id', $session->id)
                        ->effectiveOn($issueDate)
                        ->get();

                    $this->createInvoice($enrollment, $rates, $waivers, $year, $month, $issueDate);

                    $created++;
                }
            });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  Collection<int, FeeStructure>  $rates
     * @param  Collection<int, FeeWaiver>  $waivers
     */
    private function createInvoice(
        StudentEnrollment $enrollment,
        Collection $rates,
        Collection $waivers,
        int $year,
        int $month,
        CarbonImmutable $issueDate,
    ): Invoice {
        return DB::transaction(function () use ($enrollment, $rates, $waivers, $year, $month, $issueDate) {
            $dueDay = (int) ($rates->first()->due_day ?? 10);

            $invoice = Invoice::create([
                'invoice_no' => $this->numbers->invoiceNumber($year, $month),
                'student_id' => $enrollment->student_id,
                'academic_session_id' => $enrollment->academic_session_id,
                'school_class_id' => $enrollment->school_class_id,
                'section_id' => $enrollment->section_id,
                'billing_year' => $year,
                'billing_month' => $month,
                'issue_date' => $issueDate,
                'due_date' => $issueDate->day(min($dueDay, $issueDate->daysInMonth)),
                'status' => InvoiceStatus::Unpaid,
                'created_by' => auth()->id(),
            ]);

            foreach ($rates as $index => $rate) {
                $amount = (float) $rate->amount;
                $discount = $this->discountFor($waivers, $rate->fee_head_id, $amount);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'fee_head_id' => $rate->fee_head_id,
                    'name' => $rate->feeHead->name,
                    'amount' => $amount,
                    'discount' => $discount,
                    'fine' => 0,
                    'total' => round($amount - $discount, 2),
                    'sort_order' => $rate->feeHead->sort_order ?? $index,
                ]);
            }

            $invoice->recalculate();

            return $invoice;
        });
    }

    /** Head-specific waivers take precedence over blanket (NULL head) ones. */
    private function discountFor(Collection $waivers, int $feeHeadId, float $amount): float
    {
        $waiver = $waivers->firstWhere('fee_head_id', $feeHeadId)
            ?? $waivers->firstWhere('fee_head_id', null);

        return $waiver ? min($amount, $waiver->discountFor($amount)) : 0.0;
    }
}

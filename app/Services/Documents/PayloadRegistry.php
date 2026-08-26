<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\DocumentPayloadBuilder;
use App\Enums\DocumentType;
use App\Services\Documents\Payloads\AdmitCardPayload;
use App\Services\Documents\Payloads\AttendanceSheetPayload;
use App\Services\Documents\Payloads\CertificatePayload;
use App\Services\Documents\Payloads\FeeReceiptPayload;
use App\Services\Documents\Payloads\IdCardPayload;
use App\Services\Documents\Payloads\InvoicePayload;
use App\Services\Documents\Payloads\MarksheetPayload;
use App\Services\Documents\Payloads\PayslipPayload;
use App\Services\Documents\Payloads\ResultSheetPayload;
use RuntimeException;

/**
 * DocumentType -> payload builder.
 *
 * A registry rather than a match inside the generator so a school with an unusual local
 * requirement - a madrasah-specific certificate, a district-mandated form - can override
 * one type from a service provider without touching the generator or forking the package.
 */
final class PayloadRegistry
{
    /** @var array<string, class-string<DocumentPayloadBuilder>> */
    private array $map = [];

    public function __construct()
    {
        $this->map = [
            DocumentType::IdCard->value => IdCardPayload::class,
            DocumentType::AdmitCard->value => AdmitCardPayload::class,
            DocumentType::Marksheet->value => MarksheetPayload::class,
            DocumentType::Testimonial->value => CertificatePayload::class,
            DocumentType::TransferCertificate->value => CertificatePayload::class,
            DocumentType::CharacterCertificate->value => CertificatePayload::class,
            DocumentType::FeeReceipt->value => FeeReceiptPayload::class,
            DocumentType::Invoice->value => InvoicePayload::class,
            DocumentType::Payslip->value => PayslipPayload::class,
            DocumentType::AttendanceSheet->value => AttendanceSheetPayload::class,
            DocumentType::ResultSheet->value => ResultSheetPayload::class,
            DocumentType::SeatPlan->value => AttendanceSheetPayload::class,
        ];

        // Config wins, so an override needs no code change in this class.
        foreach ((array) config('pdf.payloads', []) as $type => $class) {
            $this->map[(string) $type] = $class;
        }
    }

    /** @param  class-string<DocumentPayloadBuilder>  $builder */
    public function register(DocumentType $type, string $builder): void
    {
        $this->map[$type->value] = $builder;
    }

    public function for(DocumentType $type): DocumentPayloadBuilder
    {
        $class = $this->map[$type->value] ?? null;

        if ($class === null) {
            throw new RuntimeException("No payload builder is registered for [{$type->value}].");
        }

        $builder = app($class);

        if (! $builder instanceof DocumentPayloadBuilder) {
            throw new RuntimeException("[{$class}] does not implement DocumentPayloadBuilder.");
        }

        return $builder;
    }

    public function has(DocumentType $type): bool
    {
        return isset($this->map[$type->value]);
    }
}

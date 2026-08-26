<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Every printable artefact the school issues.
 *
 * The enum drives three things at once: which Blade template renders, which page
 * setup (size / orientation / margins) the PDF renderer applies, and whether the
 * document is a single sheet or a grid of cards on one page.
 */
enum DocumentType: string
{
    use HasOptions;

    case IdCard = 'id_card';
    case AdmitCard = 'admit_card';
    case Marksheet = 'marksheet';
    case Testimonial = 'testimonial';
    case TransferCertificate = 'transfer_certificate';
    case CharacterCertificate = 'character_certificate';
    case SeatPlan = 'seat_plan';
    case FeeReceipt = 'fee_receipt';
    case Invoice = 'invoice';
    case Payslip = 'payslip';
    case AttendanceSheet = 'attendance_sheet';
    case ResultSheet = 'result_sheet';

    public function label(): string
    {
        return match ($this) {
            self::IdCard => 'ID Card',
            self::AdmitCard => 'Admit Card',
            self::Marksheet => 'Marksheet',
            self::Testimonial => 'Testimonial',
            self::TransferCertificate => 'Transfer Certificate',
            self::CharacterCertificate => 'Character Certificate',
            self::SeatPlan => 'Seat Plan',
            self::FeeReceipt => 'Fee Receipt',
            self::Invoice => 'Fee Invoice',
            self::Payslip => 'Salary Slip',
            self::AttendanceSheet => 'Attendance Sheet',
            self::ResultSheet => 'Tabulation Sheet',
        };
    }

    /** Blade view under resources/views/pdf/. */
    public function view(): string
    {
        return 'pdf.'.str_replace('_', '-', $this->value);
    }

    /**
     * Cards are laid out several to a page; everything else is one document per
     * sheet. The renderer needs to know which, because a 240-student ID card run
     * is 30 pages of grids rather than 240 pages.
     */
    public function isCard(): bool
    {
        return in_array($this, [self::IdCard, self::AdmitCard], true);
    }

    public function orientation(): string
    {
        return match ($this) {
            self::ResultSheet, self::SeatPlan, self::AttendanceSheet => 'landscape',
            default => 'portrait',
        };
    }

    /** Filename-safe slug used when writing the rendered file to storage. */
    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /**
     * Short code that heads the printed serial, e.g. TC-2026-000148.
     *
     * Certificates in Bangladesh are referenced by this number for years - a transfer
     * certificate is quoted when the student enrolls elsewhere, and a testimonial when
     * they apply to college - so the prefix must be stable and recognisable, not a bare
     * auto-increment.
     */
    public function serialPrefix(): string
    {
        return match ($this) {
            self::IdCard => 'IDC',
            self::AdmitCard => 'ADM',
            self::Marksheet => 'MS',
            self::Testimonial => 'TS',
            self::TransferCertificate => 'TC',
            self::CharacterCertificate => 'CC',
            self::SeatPlan => 'SP',
            self::FeeReceipt => 'RCP',
            self::Invoice => 'INV',
            self::Payslip => 'PS',
            self::AttendanceSheet => 'ATS',
            self::ResultSheet => 'RS',
        };
    }

    /**
     * Whether a serial is worth burning a counter on.
     *
     * Certificates and cards are legal artefacts and get one. An attendance sheet a
     * teacher reprints twice a week is not, and numbering it would leave gaps in a
     * sequence nobody reads while adding a row lock to every print.
     */
    public function needsSerial(): bool
    {
        return in_array($this, [
            self::IdCard,
            self::Testimonial,
            self::TransferCertificate,
            self::CharacterCertificate,
            self::Marksheet,
        ], true);
    }

    /**
     * Whether the document normally contains Bangla text.
     *
     * Used to warn before a batch is printed through a renderer that cannot shape
     * complex scripts. Receipts and payslips are usually numeric and English; a
     * testimonial is almost always Bangla.
     */
    public function usesBengali(): bool
    {
        return in_array($this, [
            self::Testimonial,
            self::TransferCertificate,
            self::CharacterCertificate,
            self::IdCard,
            self::Marksheet,
        ], true);
    }
}

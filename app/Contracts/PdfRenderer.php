<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\PageSetup;

/**
 * One method, three implementations, one reason.
 *
 * The PDF engine is the part of a Bangladeshi school system most likely to be forced to
 * change after deployment, and always for infrastructure reasons rather than product
 * ones. A school buys shared cPanel hosting with no Node, so Browsershot is out. Another
 * moves to a VPS a year later and wants proper Bengali shaping back. A third only ever
 * prints English and wants the fastest option.
 *
 * Every caller in this application depends on this interface and never on Dompdf, mPDF
 * or Browsershot directly, so that swap is one line in config/pdf.php rather than a
 * search-and-replace across every document template.
 */
interface PdfRenderer
{
    /**
     * Render HTML to raw PDF bytes.
     *
     * Bytes rather than a Response or a file path, because the same output has to be
     * streamed to a browser, written to disk for a batch, attached to an SMS-linked
     * download, and hashed for tamper detection. Returning bytes lets the caller decide.
     */
    public function render(string $html, PageSetup $setup): string;

    /**
     * Whether this engine shapes complex scripts such as Bengali.
     *
     * Exposed so the UI can warn an administrator BEFORE they print 400 Bangla ID
     * cards, rather than after.
     */
    public function supportsComplexScript(): bool;

    public function name(): string;

    /** True when the underlying package is actually installed. */
    public function isAvailable(): bool;
}

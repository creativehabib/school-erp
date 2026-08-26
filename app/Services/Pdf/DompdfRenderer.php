<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\PdfRenderer;
use App\Support\PageSetup;
use Barryvdh\DomPDF\Facade\Pdf as DomPdfFacade;
use RuntimeException;
use Throwable;

/**
 * Fast, tiny, everywhere - and it cannot write Bengali.
 *
 * Dompdf has no OpenType layout engine. Given "ক্ষ" it draws three separate glyphs in
 * memory order; given "কি" it puts the vowel sign after the consonant instead of before
 * it. The output is not "slightly off", it is unreadable to a Bengali speaker, and
 * embedding SolaimanLipi does not fix it because the problem is shaping, not glyph
 * coverage.
 *
 * Kept as a driver because plenty of documents in this system are English-only - a fee
 * receipt with numerals, an internal expense report - and for those Dompdf is the
 * cheapest thing that works. supportsComplexScript() returns false so the UI can refuse
 * to print Bangla certificates through it rather than producing 400 spoiled cards.
 */
final class DompdfRenderer implements PdfRenderer
{
    public function render(string $html, PageSetup $setup): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'barryvdh/laravel-dompdf is not installed. Run: composer require barryvdh/laravel-dompdf'
            );
        }

        try {
            $pdf = DomPdfFacade::loadHTML($html)
                ->setPaper(
                    $setup->isCustomSize() ? $setup->dompdfCustomPaper() : strtolower($setup->paperSize),
                    $setup->isLandscape() ? 'landscape' : 'portrait',
                )
                ->setOptions([
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont' => 'DejaVu Sans',
                    'dpi' => 96,
                ]);

            return $pdf->output();
        } catch (Throwable $e) {
            throw new RuntimeException('Dompdf failed to render the PDF: '.$e->getMessage(), previous: $e);
        }
    }

    public function supportsComplexScript(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'Dompdf (English only - cannot shape Bengali)';
    }

    public function isAvailable(): bool
    {
        return class_exists(DomPdfFacade::class);
    }
}

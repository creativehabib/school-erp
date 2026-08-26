<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\PdfRenderer;
use App\Support\PageSetup;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Headless Chromium. The correct answer for Bengali.
 *
 * Chromium shapes text with HarfBuzz, which is the same engine the student's phone and
 * the school's browser use, so what the operator previews is what prints. No pure-PHP
 * renderer can promise that for Bangla.
 *
 * Costs: Node plus a ~300MB Chromium, and roughly 150ms per document. Both are fine on a
 * VPS and impossible on most shared cPanel plans, which is exactly why MpdfRenderer
 * exists beside it.
 */
final class BrowsershotRenderer implements PdfRenderer
{
    public function render(string $html, PageSetup $setup): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'spatie/browsershot is not installed. Run: composer require spatie/browsershot && npm install puppeteer'
            );
        }

        $config = config('pdf.browsershot');

        $shot = Browsershot::html($html)
            ->showBackground()
            ->margins(
                $setup->marginTop,
                $setup->marginRight,
                $setup->marginBottom,
                $setup->marginLeft,
                'mm',
            )
            ->timeout((int) ($config['timeout'] ?? 120))
            // Wait for webfonts. Without this the first page of a batch occasionally
            // renders in a fallback font, which on a Bangla ID card means boxes.
            ->waitUntilNetworkIdle();

        if ($setup->isCustomSize()) {
            $shot->paperSize($setup->widthMm(), $setup->heightMm(), 'mm');
        } else {
            $shot->format($setup->paperSize);
        }

        if ($setup->isLandscape()) {
            $shot->landscape();
        }

        if (filled($config['node_binary'] ?? null)) {
            $shot->setNodeBinary($config['node_binary']);
        }

        if (filled($config['npm_binary'] ?? null)) {
            $shot->setNpmBinary($config['npm_binary']);
        }

        if (filled($config['chrome_path'] ?? null)) {
            $shot->setChromePath($config['chrome_path']);
        }

        if ($config['no_sandbox'] ?? true) {
            $shot->noSandbox();
        }

        try {
            return $shot->pdf();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Browsershot failed to render the PDF: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    public function supportsComplexScript(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'Headless Chromium (Browsershot)';
    }

    public function isAvailable(): bool
    {
        return class_exists(Browsershot::class);
    }
}

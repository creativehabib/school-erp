<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\PdfRenderer;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resolves the configured renderer, and degrades on purpose.
 *
 * If the configured driver's package is not installed the factory falls back rather than
 * throwing. A school does not lose the ability to print a fee receipt because someone
 * removed Node from the server; they lose Bengali shaping, which the UI can warn about.
 * Hard-failing here would take the whole accounts desk offline.
 */
final class PdfRendererFactory
{
    /** @var array<string, class-string<PdfRenderer>> */
    private const DRIVERS = [
        'browsershot' => BrowsershotRenderer::class,
        'mpdf' => MpdfRenderer::class,
        'dompdf' => DompdfRenderer::class,
    ];

    /** Preference order used when the configured driver is unavailable. */
    private const FALLBACK_ORDER = ['browsershot', 'mpdf', 'dompdf'];

    public function make(?string $driver = null): PdfRenderer
    {
        $driver ??= (string) config('pdf.driver', 'mpdf');

        if (! isset(self::DRIVERS[$driver])) {
            throw new InvalidArgumentException(
                "Unknown PDF driver [{$driver}]. Supported: ".implode(', ', array_keys(self::DRIVERS)).'.'
            );
        }

        $renderer = app(self::DRIVERS[$driver]);

        if ($renderer->isAvailable()) {
            return $renderer;
        }

        foreach (self::FALLBACK_ORDER as $candidate) {
            if ($candidate === $driver) {
                continue;
            }

            $fallback = app(self::DRIVERS[$candidate]);

            if ($fallback->isAvailable()) {
                logger()->warning('PDF driver unavailable, falling back.', [
                    'configured' => $driver,
                    'using' => $candidate,
                ]);

                return $fallback;
            }
        }

        throw new RuntimeException(
            'No PDF renderer is installed. Install one of: spatie/laravel-pdf, mpdf/mpdf, barryvdh/laravel-dompdf.'
        );
    }

    /** @return array<string, array{available: bool, complex_script: bool}> */
    public function status(): array
    {
        $status = [];

        foreach (self::DRIVERS as $key => $class) {
            /** @var PdfRenderer $renderer */
            $renderer = app($class);

            $status[$key] = [
                'available' => $renderer->isAvailable(),
                'complex_script' => $renderer->supportsComplexScript(),
            ];
        }

        return $status;
    }
}

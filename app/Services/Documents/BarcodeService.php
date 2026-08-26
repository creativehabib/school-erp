<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Support\Facades\File;
use Picqer\Barcode\BarcodeGeneratorSVG;
use RuntimeException;

/**
 * Linear barcodes, for the things a QR is wrong for.
 *
 * A school library counter uses a cheap USB laser scanner that reads Code 128 and cannot
 * read QR at all. Same for the accession numbers on book spines and, in schools that use
 * them, roll numbers on OMR-adjacent forms. So both exist side by side: QR for
 * phone-scanned identity verification, Code 128 for hardware.
 *
 * No token indirection here on purpose. A barcode on a book spine encodes the accession
 * number because that is a public inventory label, not personal data - the tradeoff that
 * makes QrCodeService use opaque tokens simply does not apply.
 */
final class BarcodeService
{
    private const CACHE_DIR = 'app/barcode-cache';

    /** Absolute path to a cached SVG barcode. */
    public function file(string $content, ?int $height = null): string
    {
        $this->assertAvailable();

        $height ??= (int) config('pdf.barcode.height', 40);
        $name = 'bc-'.sha1($content.'|'.$height).'.svg';
        $path = storage_path(self::CACHE_DIR.'/'.$name);

        if (is_file($path)) {
            return $path;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->svg($content, $height));

        return $path;
    }

    public function svg(string $content, ?int $height = null): string
    {
        $this->assertAvailable();

        return (new BarcodeGeneratorSVG())->getBarcode(
            $content,
            $this->type(),
            (int) config('pdf.barcode.width_factor', 2),
            $height ?? (int) config('pdf.barcode.height', 40),
        );
    }

    public function dataUri(string $content, ?int $height = null): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($content, $height));
    }

    /**
     * Code 128 unless overridden.
     *
     * Code 128 handles the full ASCII range, which matters because accession numbers
     * look like "LIB-2026-00841". Code 39 would force the string to be sanitised and
     * EAN-13 cannot represent it at all.
     */
    private function type(): string
    {
        $configured = (string) config('pdf.barcode.type', 'C128');

        return match (strtoupper($configured)) {
            'C39' => BarcodeGeneratorSVG::TYPE_CODE_39,
            'C93' => BarcodeGeneratorSVG::TYPE_CODE_93,
            'EAN13' => BarcodeGeneratorSVG::TYPE_EAN_13,
            'C128A' => BarcodeGeneratorSVG::TYPE_CODE_128_A,
            'C128B' => BarcodeGeneratorSVG::TYPE_CODE_128_B,
            default => BarcodeGeneratorSVG::TYPE_CODE_128,
        };
    }

    public function isAvailable(): bool
    {
        return class_exists(BarcodeGeneratorSVG::class);
    }

    private function assertAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'picqer/php-barcode-generator is not installed. Run: composer require picqer/php-barcode-generator'
            );
        }
    }
}

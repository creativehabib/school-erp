<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\PdfRenderer;
use App\Support\PageSetup;
use Mpdf\Mpdf;
use RuntimeException;
use Throwable;

/**
 * Pure PHP, with an Indic shaper. The shared-hosting answer.
 *
 * mPDF is the only widely used PHP PDF library that implements OpenType layout, which is
 * what makes Bengali conjuncts and reordered vowels come out readable. It is slower and
 * hungrier than Dompdf and imperfect on rare ligatures, but it needs nothing but PHP -
 * no Node, no Chromium, no shell_exec - so it works on the cPanel plans most Bangladeshi
 * schools actually buy.
 *
 * Two settings do all the work: `useOTL => 0xFF` turns on OpenType layout, and
 * `autoScriptToLang`/`autoLangToFont` let a single template mix English and Bangla
 * without the template author having to tag every span.
 */
final class MpdfRenderer implements PdfRenderer
{
    public function render(string $html, PageSetup $setup): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('mpdf/mpdf is not installed. Run: composer require mpdf/mpdf');
        }

        $fonts = config('pdf.fonts');
        $family = (string) ($fonts['bengali_family'] ?? 'nikosh');

        $config = [
            'mode' => 'utf-8',
            'format' => $setup->mpdfFormat(),
            'orientation' => $setup->isLandscape() ? 'L' : 'P',
            'margin_top' => $setup->marginTop,
            'margin_right' => $setup->marginRight,
            'margin_bottom' => $setup->marginBottom,
            'margin_left' => $setup->marginLeft,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => storage_path('app/mpdf'),
            // 0xFF = all OpenType features. This single flag is the difference between
            // "রফিকুল" and four disconnected letters.
            'useOTL' => 0xFF,
            'useKashida' => 75,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ];

        $fontFile = rtrim((string) ($fonts['path'] ?? ''), '/').'/'.($fonts['bengali_file'] ?? '');

        if (is_file($fontFile)) {
            $config['fontDir'] = array_merge(
                (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],
                [(string) $fonts['path']],
            );

            $config['fontdata'] = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'] + [
                $family => [
                    'R' => basename($fontFile),
                    'useOTL' => 0xFF,
                ],
            ];

            $config['default_font'] = $family;
        }

        $this->ensureTempDir($config['tempDir']);

        try {
            $mpdf = new Mpdf($config);
            $mpdf->SetTitle($setup->title ?? 'Document');
            $mpdf->SetAutoPageBreak(true, $setup->marginBottom);

            // Chunked write. A 300-page tabulation sheet handed to WriteHTML in one
            // string is where mPDF exhausts memory on a 128MB shared plan.
            foreach ($this->chunks($html) as $chunk) {
                $mpdf->WriteHTML($chunk);
            }

            return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        } catch (Throwable $e) {
            throw new RuntimeException('mPDF failed to render the PDF: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Split on page-break markers so each WriteHTML call stays small.
     *
     * @return array<int, string>
     */
    private function chunks(string $html): array
    {
        if (! str_contains($html, '<!--PAGE-->')) {
            return [$html];
        }

        return array_values(array_filter(
            array_map('trim', explode('<!--PAGE-->', $html)),
            static fn (string $chunk) => $chunk !== '',
        ));
    }

    private function ensureTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public function supportsComplexScript(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'mPDF (pure PHP, OpenType layout)';
    }

    public function isAvailable(): bool
    {
        return class_exists(Mpdf::class);
    }
}

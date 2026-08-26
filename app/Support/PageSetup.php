<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Paper geometry, passed to whichever PdfRenderer is bound.
 *
 * A value object rather than an array because the three renderers express the same
 * intent in incompatible ways - Browsershot wants CSS millimetres and a Chromium paper
 * name, mPDF wants a constructor config array, Dompdf wants a four-element point array -
 * and the translation belongs in each driver rather than at every call site.
 */
final readonly class PageSetup
{
    /** Points per millimetre, for Dompdf's custom paper array. */
    private const MM_TO_PT = 2.8346456693;

    public function __construct(
        public string $paperSize = 'A4',
        public string $orientation = 'portrait',
        public int $marginTop = 10,
        public int $marginRight = 10,
        public int $marginBottom = 10,
        public int $marginLeft = 10,
        public bool $showPageNumbers = false,
        public ?string $headerHtml = null,
        public ?string $footerHtml = null,
        public ?int $customWidthMm = null,
        public ?int $customHeightMm = null,
        public ?string $title = null,
    ) {}

    /**
     * Edge-to-edge, for ID cards and admit cards printed on pre-cut stock.
     *
     * Card layouts must not inherit A4 margins: a 10mm margin on a 54mm card eats a
     * fifth of the design and pushes the photo off the laminate.
     */
    public static function borderless(string $paperSize = 'A4', string $orientation = 'portrait'): self
    {
        return new self(
            paperSize: $paperSize,
            orientation: $orientation,
            marginTop: 0,
            marginRight: 0,
            marginBottom: 0,
            marginLeft: 0,
        );
    }

    /**
     * A single card at its exact physical size.
     *
     * Used when printing one replacement card onto card stock rather than a grid of
     * cards on A4. CR80 - the bank-card size every Bangladeshi laminating shop stocks -
     * is 85.6 x 54mm; rounded to 86 x 54 because renderers take integer millimetres and
     * the 0.4mm sits inside the bleed.
     */
    public static function card(int $widthMm = 86, int $heightMm = 54, ?string $title = null): self
    {
        return new self(
            paperSize: 'custom',
            orientation: $widthMm >= $heightMm ? 'landscape' : 'portrait',
            marginTop: 0,
            marginRight: 0,
            marginBottom: 0,
            marginLeft: 0,
            customWidthMm: $widthMm,
            customHeightMm: $heightMm,
            title: $title,
        );
    }

    /** Wide tabulation and seat-plan sheets. */
    public static function landscape(string $paperSize = 'A4', ?string $title = null): self
    {
        return new self(paperSize: $paperSize, orientation: 'landscape', title: $title);
    }

    public function withTitle(?string $title): self
    {
        return new self(
            paperSize: $this->paperSize,
            orientation: $this->orientation,
            marginTop: $this->marginTop,
            marginRight: $this->marginRight,
            marginBottom: $this->marginBottom,
            marginLeft: $this->marginLeft,
            showPageNumbers: $this->showPageNumbers,
            headerHtml: $this->headerHtml,
            footerHtml: $this->footerHtml,
            customWidthMm: $this->customWidthMm,
            customHeightMm: $this->customHeightMm,
            title: $title,
        );
    }

    public function isLandscape(): bool
    {
        return strtolower($this->orientation) === 'landscape';
    }

    public function isCustomSize(): bool
    {
        return $this->customWidthMm !== null && $this->customHeightMm !== null;
    }

    public function widthMm(): int
    {
        return $this->customWidthMm ?? 210;
    }

    public function heightMm(): int
    {
        return $this->customHeightMm ?? 297;
    }

    /**
     * mPDF's format argument.
     *
     * Two shapes, not one: a named size takes the "A4-L" suffix form, a custom size
     * takes a [width, height] array in millimetres. Passing "custom-L" would silently
     * fall back to A4 and print a 54mm card in the corner of a full sheet.
     *
     * @return string|array<int, int>
     */
    public function mpdfFormat(): string|array
    {
        if ($this->isCustomSize()) {
            return [$this->widthMm(), $this->heightMm()];
        }

        return $this->isLandscape()
            ? "{$this->paperSize}-L"
            : $this->paperSize;
    }

    /**
     * Dompdf's custom paper array, in points.
     *
     * @return array<int, float>
     */
    public function dompdfCustomPaper(): array
    {
        return [
            0.0,
            0.0,
            round($this->widthMm() * self::MM_TO_PT, 2),
            round($this->heightMm() * self::MM_TO_PT, 2),
        ];
    }

    /** @return array<string, int> */
    public function margins(): array
    {
        return [
            'top' => $this->marginTop,
            'right' => $this->marginRight,
            'bottom' => $this->marginBottom,
            'left' => $this->marginLeft,
        ];
    }

    /** CSS for an @page rule, used by the Chromium driver. */
    public function toCss(): string
    {
        $size = $this->isCustomSize()
            ? sprintf('%dmm %dmm', $this->widthMm(), $this->heightMm())
            : $this->paperSize.' '.$this->orientation;

        return sprintf(
            '@page { size: %s; margin: %dmm %dmm %dmm %dmm; }',
            $size,
            $this->marginTop,
            $this->marginRight,
            $this->marginBottom,
            $this->marginLeft,
        );
    }
}

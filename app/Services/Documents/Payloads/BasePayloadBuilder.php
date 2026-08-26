<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Contracts\DocumentPayloadBuilder;
use App\Models\Identity\SchoolProfile;
use App\Services\Documents\BarcodeService;
use App\Services\Documents\QrCodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Shared scaffolding: the school letterhead, image paths, QR/barcode helpers, formatting.
 *
 * Every builder needs the letterhead and almost every one needs an image path, and both
 * have a sharp edge that is easy to get wrong once per builder instead of once here.
 */
abstract class BasePayloadBuilder implements DocumentPayloadBuilder
{
    public function __construct(
        protected readonly QrCodeService $qr,
        protected readonly BarcodeService $barcode,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    abstract public function build(Model $subject, array $context = []): array;

    /** @return array<int, class-string<Model>> */
    abstract public function accepts(): array;

    protected function assertAccepted(Model $subject): void
    {
        foreach ($this->accepts() as $class) {
            if ($subject instanceof $class) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '%s cannot build a payload from %s. Expected one of: %s.',
            class_basename($this),
            $subject::class,
            implode(', ', array_map('class_basename', $this->accepts())),
        ));
    }

    /**
     * The letterhead block, present on every document.
     *
     * EIIN is included unconditionally because Bangladeshi school stationery carries it
     * the way a company carries a registration number - a testimonial without it is
     * questioned at the receiving institution.
     *
     * @return array<string, mixed>
     */
    protected function school(): array
    {
        $profile = SchoolProfile::query()->first();

        if ($profile === null) {
            return [
                'name_en' => config('app.name'),
                'name_bn' => null,
                'eiin' => null,
                'address_en' => null,
                'address_bn' => null,
                'phone' => null,
                'email' => null,
                'website' => null,
                'logo' => null,
                'signature' => null,
                'head_teacher_name' => null,
                'head_teacher_designation' => null,
                'established_year' => null,
            ];
        }

        return [
            'name_en' => $profile->name_en,
            'name_bn' => $profile->name_bn,
            'eiin' => $profile->eiin,
            'board' => $profile->board?->name,
            'board_school_code' => $profile->board_school_code,
            'address_en' => $profile->address_en,
            'address_bn' => $profile->address_bn,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'website' => $profile->website,
            'logo' => $this->imagePath($profile->logo_path),
            'signature' => $this->imagePath($profile->signature_path),
            'head_teacher_name' => $profile->head_teacher_name,
            'head_teacher_designation' => $profile->head_teacher_designation,
            'established_year' => $profile->established_year,
        ];
    }

    /**
     * An absolute filesystem path for an image, or null.
     *
     * This is the single most common cause of blank photos on printed cards, so it is
     * worth being explicit. A PDF renderer running in a queue worker has no HTTP context:
     * a `/storage/photos/x.jpg` URL resolves to nothing, and Dompdf with isRemoteEnabled
     * off refuses http:// entirely. Only a real path on disk works in all three engines,
     * and a missing file must degrade to null so a student with no photo still gets a
     * card instead of an exception mid-batch.
     */
    protected function imagePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $disk = Storage::disk(config('filesystems.default'));

        try {
            if ($disk->exists($path)) {
                $absolute = $disk->path($path);

                return is_file($absolute) ? $absolute : null;
            }
        } catch (\Throwable) {
            // Cloud disks have no local path. Nothing to embed; fall through to null.
            return null;
        }

        $public = storage_path('app/public/'.ltrim($path, '/'));

        return is_file($public) ? $public : null;
    }

    /**
     * @return array{token: string, url: string, src: string}|array{token: null, url: null, src: null}
     */
    protected function qrFor(Model $holder, string $purpose = 'id_card', ?int $sessionId = null, ?int $validDays = null): array
    {
        try {
            return $this->qr->forHolder($holder, $purpose, $sessionId, $validDays);
        } catch (\Throwable $e) {
            // A missing QR package must not stop a certificate from printing.
            logger()->warning('QR generation failed.', ['error' => $e->getMessage()]);

            return ['token' => null, 'url' => null, 'src' => null];
        }
    }

    protected function barcodeFor(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return $this->barcode->file($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function money(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }

    protected function date(mixed $value, string $format = 'd/m/Y'): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->format($format);
    }

    /**
     * The "issued on" block, and why it is generated here rather than in the template.
     *
     * A certificate's own printed date must come from the payload so that a reprint shows
     * the ORIGINAL issue date. Putting `now()` in the Blade template would silently
     * re-date a 2026 transfer certificate to whenever someone happened to reprint it,
     * which changes the meaning of the document.
     *
     * @return array<string, mixed>
     */
    protected function issuance(?Carbon $at = null): array
    {
        $at ??= now();

        return [
            'date' => $at->format('d/m/Y'),
            'date_long' => $at->format('j F Y'),
            'year' => $at->year,
            'timestamp' => $at->toDateTimeString(),
        ];
    }

    /**
     * Amount in words, for cheques and fee receipts.
     *
     * Written out rather than pulled from a package because the Bangladeshi convention is
     * the Indian numbering system - lakh and crore, not million and billion. A receipt
     * reading "one million two hundred thousand taka" is not what an auditor here expects
     * to see, and "twelve lakh taka" is.
     */
    protected function amountInWords(float $amount): string
    {
        $taka = (int) floor($amount);
        $poisha = (int) round(($amount - $taka) * 100);

        $words = $taka === 0 ? 'zero' : $this->indianWords($taka);
        $result = ucfirst(trim($words)).' taka';

        if ($poisha > 0) {
            $result .= ' and '.trim($this->indianWords($poisha)).' poisha';
        }

        return $result.' only';
    }

    private function indianWords(int $number): string
    {
        $units = [
            0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
            11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
            15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty', 40 => 'forty',
            50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety',
        ];

        if ($number < 0) {
            return 'minus '.$this->indianWords(-$number);
        }

        if ($number < 21) {
            return $units[$number];
        }

        if ($number < 100) {
            return $units[intdiv($number, 10) * 10].($number % 10 !== 0 ? ' '.$units[$number % 10] : '');
        }

        if ($number < 1000) {
            return $units[intdiv($number, 100)].' hundred'
                .($number % 100 !== 0 ? ' '.$this->indianWords($number % 100) : '');
        }

        // The two lines that make this Bangladeshi rather than Western: group by
        // thousand, then lakh (100 thousand), then crore (100 lakh).
        if ($number < 100000) {
            return $this->indianWords(intdiv($number, 1000)).' thousand'
                .($number % 1000 !== 0 ? ' '.$this->indianWords($number % 1000) : '');
        }

        if ($number < 10000000) {
            return $this->indianWords(intdiv($number, 100000)).' lakh'
                .($number % 100000 !== 0 ? ' '.$this->indianWords($number % 100000) : '');
        }

        return $this->indianWords(intdiv($number, 10000000)).' crore'
            .($number % 10000000 !== 0 ? ' '.$this->indianWords($number % 10000000) : '');
    }
}

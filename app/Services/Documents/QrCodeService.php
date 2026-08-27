<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Documents\CardToken;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * QR codes for cards, and the security model behind them.
 *
 * WHAT GOES IN THE QR. A URL containing an opaque 32-character token and nothing else.
 * Not the student's name, not their phone number, not their primary key. An ID card is a
 * public object - it hangs outside a uniform, it gets photographed, it gets dropped in
 * the street - so anything encoded in it is effectively published. Encode `student_id=41`
 * and you have handed anyone who finds one card the ability to enumerate the entire
 * student body by editing a number.
 *
 * WHY A SIGNATURE TOO. The URL carries an HMAC of the token. Online, the server looks
 * the token up and the signature is redundant. Offline - a gate guard with a scanner app
 * and no data connection, which is the normal case at a Bangladeshi school gate - the
 * signature is the only thing that distinguishes a real card from one printed at a photo
 * shop with a QR pointing at a lookalike token. The secret is APP_KEY, so a forged card
 * cannot be produced without server access.
 *
 * WHY FILES, NOT DATA URIS, BY DEFAULT. All three PDF renderers read local file paths
 * reliably. Data-URI support is uneven: Dompdf handles PNG data URIs but chokes on SVG,
 * and older mPDF builds refuse SVG data URIs outright. Writing a cached file and
 * referencing its path is the one approach that works everywhere, and the cache means a
 * 400-card batch generates each unique QR once.
 */
final class QrCodeService
{
    private const CACHE_DIR = 'app/qr-cache';

    /**
     * Mint or reuse the holder's token and return everything a template needs.
     *
     * @return array{token: string, url: string, src: string}
     */
    public function forHolder(
        Model $holder,
        string $purpose = 'id_card',
        ?int $academicSessionId = null,
        ?int $validDays = null,
        ?int $size = null,
    ): array {
        $card = CardToken::issueFor($holder, $purpose, $academicSessionId, $validDays);
        $url = $this->signedUrl($card);

        return [
            'token' => $card->token,
            'url' => $url,
            'src' => $this->file($url, $size),
        ];
    }

    /**
     * The verification URL with its offline signature appended.
     *
     * Truncated to 16 hex characters - 64 bits. Full-length would be 64 characters and
     * would push the QR to a density that a cheap phone camera cannot read off a 15mm
     * printed square. 64 bits of HMAC is far beyond what forging a school ID card is
     * worth to anyone.
     */
    public function signedUrl(CardToken $card): string
    {
        $signature = substr($this->signature($card->token), 0, 16);

        return $card->verifyUrl().'?s='.$signature;
    }

    public function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret());
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        $expected = substr($this->signature($payload), 0, strlen($signature));

        // Constant-time compare. A naive === leaks the correct prefix through timing,
        // which is a real (if slow) way to forge a signature.
        return hash_equals($expected, $signature);
    }

    /**
     * Absolute path to a cached QR image, generating it if absent.
     *
     * Cached on a hash of content plus size, so reprinting a card three times renders
     * the QR once, and so a batch of 400 cards for one exam shares nothing it should not.
     */
    public function file(string $content, ?int $size = null): string
    {
        $this->assertAvailable();

        $size ??= (int) config('pdf.qr.size', 140);
        $extension = $this->preferredFormat();
        $name = 'qr-'.sha1($content.'|'.$size.'|'.$extension).'.'.$extension;
        $path = storage_path(self::CACHE_DIR.'/'.$name);

        if (is_file($path)) {
            return $path;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->raw($content, $size, $extension));

        return $path;
    }

    /** Raw image bytes, for streaming or embedding by the caller. */
    public function raw(string $content, ?int $size = null, ?string $format = null): string
    {
        $this->assertAvailable();

        $size ??= (int) config('pdf.qr.size', 140);
        $format = $this->supportedFormat($format ?? $this->preferredFormat());
        $backEnd = $format === 'png'
            ? new ImagickImageBackEnd('png')
            : new SvgImageBackEnd;
        $renderer = new ImageRenderer(
            new RendererStyle($size, (int) config('pdf.qr.margin', 0)),
            $backEnd,
        );

        return (new Writer($renderer))->writeString($content);
    }

    public function dataUri(string $content, ?int $size = null, ?string $format = null): string
    {
        $format = $this->supportedFormat($format ?? $this->preferredFormat());
        $mime = $format === 'svg' ? 'image/svg+xml' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($this->raw($content, $size, $format));
    }

    /**
     * PNG when Imagick is present, SVG otherwise.
     *
     * The PNG backend requires Imagick, so on the many
     * shared hosts without the extension the choice is SVG or nothing. SVG also prints
     * sharper, so this is not purely a fallback.
     */
    public function preferredFormat(): string
    {
        $configured = (string) config('pdf.qr.format', 'svg');

        if ($configured === 'png' && ! extension_loaded('imagick')) {
            return 'svg';
        }

        return $configured;
    }

    /**
     * Wipe the QR cache.
     *
     * Called after mass revocation. The images themselves are not secret, but leaving
     * thousands of files for revoked tokens on a 5GB shared plan is how a school runs out
     * of disk and cannot print anything.
     */
    public function clearCache(): int
    {
        $dir = storage_path(self::CACHE_DIR);

        if (! is_dir($dir)) {
            return 0;
        }

        $files = File::files($dir);
        File::delete(array_map(static fn ($file) => $file->getPathname(), $files));

        return count($files);
    }

    public function isAvailable(): bool
    {
        return class_exists(Writer::class) && class_exists(ImageRenderer::class);
    }

    private function assertAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'bacon/bacon-qr-code is not installed. Run: composer install'
            );
        }
    }

    private function supportedFormat(string $format): string
    {
        if ($format === 'png' && extension_loaded('imagick')) {
            return 'png';
        }

        return 'svg';
    }

    private function secret(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set; card signatures cannot be generated.');
        }

        return str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true) ?: $key
            : $key;
    }
}

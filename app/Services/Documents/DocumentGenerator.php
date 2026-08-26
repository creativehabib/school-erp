<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Documents\DocumentBatch;
use App\Models\Documents\DocumentTemplate;
use App\Models\Documents\GeneratedDocument;
use App\Services\Accounts\DocumentNumberService;
use App\Services\Pdf\PdfRendererFactory;
use App\Support\PageSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Throwable;

/**
 * Payload -> HTML -> PDF -> stored file, with the record that proves it happened.
 *
 * The one design decision worth arguing about is here: a batch of 400 ID cards is rendered
 * as ONE HTML document with page breaks, not as 400 PDFs that are then merged.
 *
 * Merging needs FPDI or a shell tool, both of which are absent on the shared hosting many
 * Bangladeshi schools run on, and 400 separate render calls means 400 Chromium launches -
 * roughly a minute of pure process startup. Assembling one document instead needs no extra
 * dependency, renders in a single pass, and lays 10 cards to a sheet properly because the
 * layout engine can see the whole grid. The tradeoff is peak memory, which is why fragments
 * are streamed to a temp file rather than concatenated in a string.
 */
final class DocumentGenerator
{
    public function __construct(
        private readonly PdfRendererFactory $renderers,
        private readonly PayloadRegistry $payloads,
        private readonly TemplateRenderer $templates,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function payload(DocumentType $type, Model $subject, array $context = []): array
    {
        $context['type'] ??= $type;

        return $this->payloads->for($type)->build($subject, $context);
    }

    /**
     * HTML for one document, ready to render or to preview in a browser.
     *
     * @param  array<string, mixed>  $payload
     */
    public function html(DocumentType $type, array $payload, ?DocumentTemplate $template = null): string
    {
        if ($template?->isUserAuthored()) {
            return $this->renderUserTemplate($type, $template, $payload);
        }

        return View::make($type->view(), [
            'payload' => $payload,
            'template' => $template,
            'setup' => $this->setupFor($type, $template),
        ])->render();
    }

    /** Raw PDF bytes for one document. */
    public function pdf(DocumentType $type, array $payload, ?DocumentTemplate $template = null): string
    {
        return $this->renderers->make()->render(
            $this->html($type, $payload, $template),
            $this->setupFor($type, $template),
        );
    }

    /**
     * Generate, store and record one document.
     *
     * Wrapped so a single bad record - a student with a corrupt photo file, a result with
     * no snapshot - is recorded as a failed row with its error message instead of aborting
     * the run. In a 1,200-card batch, "3 failed, here is why" is usable; a stack trace and
     * nothing printed is not.
     *
     * @param  array<string, mixed>  $context
     */
    public function generate(
        DocumentType $type,
        Model $subject,
        ?DocumentTemplate $template = null,
        array $context = [],
        ?DocumentBatch $batch = null,
        ?int $issuedBy = null,
    ): GeneratedDocument {
        $template ??= DocumentTemplate::defaultFor($type);

        $document = GeneratedDocument::create([
            'document_batch_id' => $batch?->getKey(),
            'document_template_id' => $template?->getKey(),
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'status' => DocumentStatus::Processing,
            'issued_by' => $issuedBy ?? auth()->id(),
        ]);

        try {
            // The serial is allocated BEFORE rendering, because it is printed on the
            // document. Allocating it after would mean the stored serial and the serial on
            // the paper could differ if the render failed and was retried.
            if ($type->needsSerial()) {
                $serial = $this->allocateSerial($type);
                $document->forceFill(['serial_no' => $serial])->save();
                $context['serial_no'] = $serial;
            }

            $payload = $this->payload($type, $subject, $context);
            $payload['serial_no'] = $document->serial_no;

            $bytes = $this->pdf($type, $payload, $template);
            $path = $this->store($type, $document, $bytes);

            $document->forceFill([
                'status' => DocumentStatus::Generated,
                'file_path' => $path,
                'file_size' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'payload' => $payload,
                'issued_at' => now(),
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            report($e);

            $document->forceFill([
                'status' => DocumentStatus::Failed,
                'error' => \Illuminate\Support\Str::limit($e->getMessage(), 1000),
            ])->save();
        }

        return $document;
    }

    /**
     * One PDF containing many documents.
     *
     * @param  iterable<int, array{subject: Model, context?: array<string, mixed>}>  $items
     * @return array{bytes: string, rendered: int, failures: array<int, string>}
     */
    public function renderCollection(
        DocumentType $type,
        iterable $items,
        ?DocumentTemplate $template = null,
        array $sharedContext = [],
    ): array {
        $template ??= DocumentTemplate::defaultFor($type);

        // Fragments go to a temp file, not a string. A 1,200-student tabulation run
        // assembled in memory is tens of megabytes of HTML before the renderer even
        // starts, and PHP's memory_limit on shared hosting is often 128M.
        $handle = tmpfile();

        if ($handle === false) {
            throw new RuntimeException('Could not open a temporary file for batch assembly.');
        }

        $rendered = 0;
        $failures = [];
        $perPage = max(1, (int) ($template?->per_page ?? 1));
        $onPage = 0;

        foreach ($items as $item) {
            $subject = $item['subject'];

            try {
                $payload = $this->payload($type, $subject, ($item['context'] ?? []) + $sharedContext);
                $fragment = $this->fragment($type, $payload, $template);

                // Page break between groups, not between every item: cards are laid
                // several to a sheet, certificates are one per sheet.
                if ($onPage > 0 && $onPage % $perPage === 0) {
                    fwrite($handle, '<!--PAGE-->');
                }

                fwrite($handle, $fragment);
                $onPage++;
                $rendered++;
            } catch (Throwable $e) {
                $failures[] = sprintf('%s #%s: %s', class_basename($subject), $subject->getKey(), $e->getMessage());
            }
        }

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        if ($rendered === 0) {
            throw new RuntimeException('Nothing could be rendered. First error: '.($failures[0] ?? 'unknown'));
        }

        $html = $this->wrapCollection($type, $body, $template);

        return [
            'bytes' => $this->renderers->make()->render($html, $this->setupFor($type, $template)),
            'rendered' => $rendered,
            'failures' => $failures,
        ];
    }

    /**
     * A single document's markup WITHOUT the html/head wrapper, for batch assembly.
     *
     * Types opt in by shipping a `.fragment` view. Falling back to the full view would
     * embed a complete html document inside another one, which Chromium tolerates and
     * mPDF does not.
     *
     * @param  array<string, mixed>  $payload
     */
    private function fragment(DocumentType $type, array $payload, ?DocumentTemplate $template): string
    {
        if ($template?->isUserAuthored()) {
            return $this->templates->render((string) $template->body, $payload);
        }

        $fragmentView = $type->view().'-fragment';

        if (View::exists($fragmentView)) {
            return View::make($fragmentView, [
                'payload' => $payload,
                'template' => $template,
            ])->render();
        }

        return $this->stripDocumentShell($this->html($type, $payload, $template));
    }

    /**
     * Last resort for a type with no fragment view: keep only what was inside <body>.
     *
     * Crude on purpose - it also has to lift the <style> block out of the head, or every
     * document after the first loses its styling.
     */
    private function stripDocumentShell(string $html): string
    {
        $styles = '';

        if (preg_match_all('/<style\b[^>]*>.*?<\/style>/is', $html, $matches) === 1) {
            $styles = implode('', $matches[0]);
        }

        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $body) === 1) {
            return $styles.$body[1];
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderUserTemplate(DocumentType $type, DocumentTemplate $template, array $payload): string
    {
        $body = $this->templates->render((string) $template->body, $payload);
        $styles = (string) ($template->styles ?? '');
        $setup = $this->setupFor($type, $template);

        return $this->shell($body, $styles, $setup, $template);
    }

    private function wrapCollection(DocumentType $type, string $body, ?DocumentTemplate $template): string
    {
        if ($template?->isUserAuthored()) {
            return $this->shell($body, (string) ($template->styles ?? ''), $this->setupFor($type, $template), $template);
        }

        $layout = 'pdf.batch';

        if (View::exists($layout)) {
            return View::make($layout, [
                'body' => $body,
                'type' => $type,
                'template' => $template,
                'setup' => $this->setupFor($type, $template),
            ])->render();
        }

        return $this->shell($body, '', $this->setupFor($type, $template), $template);
    }

    /**
     * The html/head wrapper for user-authored and fallback markup.
     *
     * The font stack is the reason this is not a one-liner. `font-family` must name the
     * Bengali family first, because a stack that starts with a Latin font makes the
     * renderer fall back per-glyph and Bangla text ends up in whatever substitute the
     * engine picks - which under mPDF means unshaped output even with useOTL on.
     */
    private function shell(string $body, string $styles, PageSetup $setup, ?DocumentTemplate $template): string
    {
        $bengali = (string) config('pdf.fonts.bengali_family', 'nikosh');
        $fallback = (string) config('pdf.fonts.fallback_family', 'DejaVu Sans');
        $background = $template?->background_path;
        $backgroundCss = '';

        if (filled($background)) {
            $absolute = is_file($background) ? $background : storage_path('app/public/'.ltrim($background, '/'));

            if (is_file($absolute)) {
                $backgroundCss = sprintf(
                    'body { background-image: url("%s"); background-size: cover; background-repeat: no-repeat; }',
                    $absolute,
                );
            }
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="bn">
        <head>
            <meta charset="utf-8">
            <title>{$this->escape($setup->title ?? 'Document')}</title>
            <style>
                {$setup->toCss()}
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    font-family: "{$bengali}", "{$fallback}", sans-serif;
                    font-size: 12px;
                    color: #111827;
                }
                table { border-collapse: collapse; width: 100%; }
                .page-break { page-break-after: always; }
                {$backgroundCss}
                {$styles}
            </style>
        </head>
        <body>
        {$body}
        </body>
        </html>
        HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function setupFor(DocumentType $type, ?DocumentTemplate $template = null): PageSetup
    {
        if ($template !== null) {
            return $template->pageSetup();
        }

        return (new PageSetup(
            paperSize: 'A4',
            orientation: $type->orientation(),
            marginTop: $type->isCard() ? 0 : 12,
            marginRight: $type->isCard() ? 0 : 12,
            marginBottom: $type->isCard() ? 0 : 12,
            marginLeft: $type->isCard() ? 0 : 12,
        ))->withTitle($type->label());
    }

    /**
     * Reserve a printed serial, gap-free, under a row lock.
     *
     * Scoped by calendar year so the number resets each January and stays short. TCs are
     * quoted verbally and written on forms; TC-2026-000148 is transcribable and
     * TC-0000000000148 is not.
     */
    private function allocateSerial(DocumentType $type): string
    {
        $year = now()->year;

        return $this->numbers->next(
            key: 'document.'.$type->value,
            scope: (string) $year,
            prefix: $type->serialPrefix()."-{$year}-",
            padding: 6,
        );
    }

    /**
     * Write the PDF and return its relative path.
     *
     * Foldered by type and year-month. A school printing receipts daily reaches tens of
     * thousands of files in a year, and a single flat directory makes both the filesystem
     * and any attempt to back it up miserable.
     */
    private function store(DocumentType $type, GeneratedDocument $document, string $bytes): string
    {
        $path = sprintf(
            '%s/%s/%s/%s-%d.pdf',
            trim((string) config('pdf.directory', 'documents'), '/'),
            $type->slug(),
            now()->format('Y-m'),
            $type->slug(),
            $document->getKey(),
        );

        Storage::disk($this->disk())->put($path, $bytes);

        return $path;
    }

    /** Batch output, kept beside the batch record. */
    public function storeBatch(DocumentBatch $batch, string $bytes): string
    {
        $path = sprintf(
            '%s/batches/%s/batch-%d.pdf',
            trim((string) config('pdf.directory', 'documents'), '/'),
            $batch->created_at?->format('Y-m') ?? now()->format('Y-m'),
            $batch->getKey(),
        );

        Storage::disk($this->disk())->put($path, $bytes);

        return $path;
    }

    public function disk(): string
    {
        return (string) config('pdf.disk', 'local');
    }

    /**
     * Record the payload snapshot for every subject in a merged batch.
     *
     * The merged PDF is one file, but each student still needs a row: a serial number for
     * their certificate, an audit trail, and the ability to answer "was a card ever issued
     * to this student" without opening a 400-page PDF.
     *
     * @param  array<int, array{subject: Model, payload: array<string, mixed>}>  $entries
     */
    public function recordBatchDocuments(DocumentBatch $batch, array $entries, ?int $issuedBy = null): void
    {
        DB::transaction(function () use ($batch, $entries, $issuedBy): void {
            foreach ($entries as $entry) {
                GeneratedDocument::create([
                    'document_batch_id' => $batch->getKey(),
                    'document_template_id' => $batch->document_template_id,
                    'type' => $batch->type,
                    'subject_type' => $entry['subject']->getMorphClass(),
                    'subject_id' => $entry['subject']->getKey(),
                    'serial_no' => $entry['payload']['serial_no'] ?? null,
                    'status' => DocumentStatus::Generated,
                    'payload' => $entry['payload'],
                    'issued_by' => $issuedBy,
                    'issued_at' => now(),
                ]);
            }
        });
    }
}

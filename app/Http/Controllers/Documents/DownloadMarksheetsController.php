<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Exam\ExamResult;
use App\Services\Documents\Payloads\MarksheetPayload;
use App\Services\Pdf\DompdfRenderer;
use App\Support\PageSetup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadMarksheetsController extends Controller
{
    public function __invoke(Request $request, MarksheetPayload $payloadBuilder, DompdfRenderer $pdfRenderer): StreamedResponse
    {
        Gate::authorize('academic.marksheet.generate');
        $resultIds = $this->resultIds((string) $request->query('ids'));
        $results = ExamResult::query()->whereKey($resultIds)->get();

        abort_unless($results->count() === count($resultIds), 404);

        return $this->download($results, $payloadBuilder, $pdfRenderer);
    }

    /** @return array<int, int> */
    private function resultIds(string $ids): array
    {
        $resultIds = collect(explode(',', $ids))
            ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id > 0)
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values();

        abort_if($resultIds->isEmpty() || $resultIds->count() > 500, 422);

        return $resultIds->all();
    }

    /** @param Collection<int, ExamResult> $results */
    public function download(Collection $results, MarksheetPayload $payloadBuilder, DompdfRenderer $pdfRenderer): StreamedResponse
    {
        $marksheets = $results->map(fn (ExamResult $result): array => $this->embedImages($payloadBuilder->build($result)))->all();
        $html = View::make('pdf.marksheet', ['marksheets' => $marksheets])->render();
        $bytes = $pdfRenderer->render($html, new PageSetup(title: 'Student Marksheets'));

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            'student-marksheets-'.now()->format('Y-m-d-His').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function embedImages(array $payload): array
    {
        foreach (['logo', 'signature'] as $key) {
            $path = $payload['school'][$key] ?? null;

            if (is_string($path) && is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';
                $payload['school'][$key] = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return $payload;
    }
}

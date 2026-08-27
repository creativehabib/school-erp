<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Exam\ExamResult;
use App\Models\User;
use App\Services\Documents\Payloads\MarksheetPayload;
use App\Services\Pdf\DompdfRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadOwnMarksheetController extends Controller
{
    public function __invoke(
        Request $request,
        ExamResult $result,
        MarksheetPayload $payloadBuilder,
        DompdfRenderer $pdfRenderer,
        DownloadMarksheetsController $downloader,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $result->published_at !== null, 404);

        $studentId = $result->enrollment()->value('student_id');
        $ownsResult = match (true) {
            $user->hasRole(RoleName::Student->value) => $user->student()->whereKey($studentId)->exists(),
            $user->hasRole(RoleName::Guardian->value) => $user->guardian()
                ->whereHas('students', fn ($query) => $query->whereKey($studentId))
                ->exists(),
            default => false,
        };

        abort_unless($ownsResult, 404);

        return $downloader->download(new Collection([$result]), $payloadBuilder, $pdfRenderer);
    }
}

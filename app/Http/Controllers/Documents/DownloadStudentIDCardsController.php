<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Academic\Student;
use App\Models\Identity\SchoolProfile;
use App\Services\Documents\QrCodeService;
use App\Services\Pdf\DompdfRenderer;
use App\Support\PageSetup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadStudentIDCardsController extends Controller
{
    public function __invoke(Request $request, QrCodeService $qrCodes, DompdfRenderer $pdfRenderer): StreamedResponse
    {
        Gate::authorize('document.id_card.generate');
        $studentIds = $this->studentIds((string) $request->query('ids'));

        $students = Student::query()
            ->active()
            ->with([
                'currentEnrollment.schoolClass:id,name',
                'currentEnrollment.section:id,name',
            ])
            ->whereKey($studentIds)
            ->get();

        abort_unless($students->count() === count($studentIds), 404);

        $school = SchoolProfile::query()->first();
        $cards = $this->cards($students, $qrCodes);
        $html = View::make('pdf.id-card', [
            'cards' => $cards,
            'school' => $school,
            'schoolLogo' => $this->publicImageDataUri($school?->logo_path),
        ])->render();
        $bytes = $pdfRenderer->render($html, PageSetup::borderless('A4'));

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            'student-id-cards-'.now()->format('Y-m-d-His').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @return array<int, int> */
    private function studentIds(string $ids): array
    {
        $studentIds = collect(explode(',', $ids))
            ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id > 0)
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values();

        abort_if($studentIds->isEmpty() || $studentIds->count() > 100, 422);

        return $studentIds->all();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array{student: Student, qr: string, photo: string|null}>
     */
    private function cards(Collection $students, QrCodeService $qrCodes): array
    {
        return $students->map(fn (Student $student): array => [
            'student' => $student,
            'qr' => $qrCodes->dataUri($student->qrPayload(), 140, 'png'),
            'photo' => $this->publicImageDataUri($student->photo_path),
        ])->all();
    }

    private function publicImageDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $absolutePath = storage_path('app/public/'.$path);

        if (! is_file($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }
}

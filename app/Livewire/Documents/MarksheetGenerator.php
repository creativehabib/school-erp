<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Enums\RoleName;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudentEnrollment;
use App\Models\Exam\Exam;
use App\Models\Exam\ExamResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Marksheet Generator')]
class MarksheetGenerator extends Component
{
    public ?int $examId = null;

    public ?int $schoolClassId = null;

    public ?int $sectionId = null;

    public function mount(): void
    {
        Gate::authorize('academic.marksheet.generate');
    }

    /** @return Collection<int, Exam> */
    #[Computed]
    public function exams(): Collection
    {
        return Exam::query()
            ->whereHas('results')
            ->with('academicSession:id,name')
            ->latest('id')
            ->get(['id', 'academic_session_id', 'name']);
    }

    /** @return Collection<int, SchoolClass> */
    #[Computed]
    public function classes(): Collection
    {
        if ($this->examId === null) {
            return new Collection;
        }

        return SchoolClass::query()
            ->active()
            ->whereHas('enrollments.examResults', fn (Builder $query) => $query->where('exam_id', $this->examId))
            ->whereHas('sections', fn (Builder $query) => $this->constrainSectionAccess($query))
            ->ordered()
            ->get(['id', 'name', 'level']);
    }

    /** @return Collection<int, Section> */
    #[Computed]
    public function sections(): Collection
    {
        return $this->constrainSectionAccess(Section::query()->active())
            ->when($this->schoolClassId, fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->when(! $this->schoolClassId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'school_class_id', 'name']);
    }

    /** @return Collection<int, StudentEnrollment> */
    #[Computed]
    public function students(): Collection
    {
        if ($this->examId === null || $this->schoolClassId === null || $this->sectionId === null) {
            return new Collection;
        }

        return StudentEnrollment::query()
            ->where('school_class_id', $this->schoolClassId)
            ->where('section_id', $this->sectionId)
            ->whereHas('examResults', fn (Builder $query) => $query->where('exam_id', $this->examId))
            ->with([
                'student:id,name_en,admission_no',
                'examResults' => fn (Builder $query) => $query
                    ->where('exam_id', $this->examId)
                    ->select(['id', 'exam_id', 'student_enrollment_id', 'gpa', 'grade', 'is_failed']),
            ])
            ->orderBy('class_roll')
            ->get(['id', 'student_id', 'class_roll']);
    }

    public function updatedExamId(): void
    {
        $this->schoolClassId = null;
        $this->sectionId = null;
        unset($this->classes, $this->sections, $this->students);
    }

    public function updatedSchoolClassId(): void
    {
        $this->sectionId = null;
        unset($this->sections, $this->students);
    }

    public function updatedSectionId(): void
    {
        unset($this->students);
    }

    public function generate(int $resultId): void
    {
        $this->redirectToResults([$resultId]);
    }

    public function generateBulk(): void
    {
        $this->redirectToResults($this->filteredResultIds());
    }

    /** @param array<int, int|string> $resultIds */
    private function redirectToResults(array $resultIds): void
    {
        Gate::authorize('academic.marksheet.generate');
        $allowedIds = collect($this->filteredResultIds());
        $requestedIds = collect($resultIds)->map(fn (int|string $id): int => (int) $id)->unique()->values();

        if ($requestedIds->isEmpty() || $requestedIds->diff($allowedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'marksheets' => __('One or more results are outside the selected section.'),
            ]);
        }

        $url = URL::temporarySignedRoute(
            'documents.marksheets.download',
            now()->addMinutes(5),
            ['ids' => $requestedIds->implode(',')],
        );

        $this->redirect($url);
    }

    /** @return array<int, int|string> */
    private function filteredResultIds(): array
    {
        return $this->students
            ->flatMap(fn (StudentEnrollment $enrollment): array => $enrollment->examResults->modelKeys())
            ->values()
            ->all();
    }

    private function constrainSectionAccess(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user instanceof User && $user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Admin->value])) {
            return $query;
        }

        $sessionId = $this->examId === null
            ? null
            : Exam::query()->whereKey($this->examId)->value('academic_session_id');
        $employeeId = $user instanceof User ? $user->employee()->value('id') : null;

        if ($sessionId === null || $employeeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('teacherAssignments', fn (Builder $assignment) => $assignment
            ->where('academic_session_id', $sessionId)
            ->where('employee_id', $employeeId));
    }
}

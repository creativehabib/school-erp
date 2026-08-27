<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Actions\Exam\SaveMarks;
use App\Enums\RoleName;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudentEnrollment;
use App\Models\Exam\Exam;
use App\Models\Exam\ExamSubject;
use App\Models\Exam\Mark;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Marks Entry')]
class MarksEntry extends Component
{
    public ?int $examId = null;

    public ?int $schoolClassId = null;

    public ?int $sectionId = null;

    public ?int $examSubjectId = null;

    /**
     * @var array<int, array{written: string, mcq: string, practical: string, is_absent: bool, total: string, grade: string, gpa: string}>
     */
    public array $marksData = [];

    public function mount(): void
    {
        Gate::authorize('academic.mark.enter');
    }

    /** @return Collection<int, Exam> */
    #[Computed]
    public function exams(): Collection
    {
        return Exam::query()
            ->openForEntry()
            ->with('academicSession:id,name')
            ->latest('id')
            ->get(['id', 'academic_session_id', 'name', 'term']);
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
            ->whereHas('sections', fn (Builder $query) => $this->constrainSectionAccess($query))
            ->whereHas('classSubjects.examSubjects', fn (Builder $query) => $query->where('exam_id', $this->examId))
            ->ordered()
            ->get(['id', 'name', 'level']);
    }

    /** @return Collection<int, Section> */
    #[Computed]
    public function sections(): Collection
    {
        return $this->accessibleSectionsQuery()
            ->when($this->schoolClassId, fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->when(! $this->schoolClassId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'school_class_id', 'name']);
    }

    /** @return Collection<int, ExamSubject> */
    #[Computed]
    public function subjects(): Collection
    {
        if ($this->examId === null || $this->schoolClassId === null) {
            return new Collection;
        }

        return ExamSubject::query()
            ->where('exam_id', $this->examId)
            ->whereHas('classSubject', fn (Builder $query) => $query
                ->where('school_class_id', $this->schoolClassId)
                ->where('is_active', true))
            ->with('classSubject.subject:id,name,code')
            ->ordered()
            ->get();
    }

    #[Computed]
    public function paper(): ?ExamSubject
    {
        if ($this->examSubjectId === null) {
            return null;
        }

        return ExamSubject::query()
            ->with(['exam', 'classSubject.subject'])
            ->where('exam_id', $this->examId)
            ->whereHas('classSubject', fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->find($this->examSubjectId);
    }

    /** @return Collection<int, StudentEnrollment> */
    #[Computed]
    public function students(): Collection
    {
        $paper = $this->paper;

        if ($paper === null || $this->sectionId === null) {
            return new Collection;
        }

        return StudentEnrollment::query()
            ->current()
            ->where('academic_session_id', $paper->exam->academic_session_id)
            ->where('school_class_id', $this->schoolClassId)
            ->where('section_id', $this->sectionId)
            ->with([
                'student:id,name_en,admission_no',
                'marks' => fn (Builder $query) => $query
                    ->where('exam_subject_id', $paper->id)
                    ->select([
                        'id', 'student_enrollment_id', 'exam_subject_id', 'cq_marks',
                        'mcq_marks', 'practical_marks', 'obtained_marks', 'is_absent',
                        'grade', 'gpa',
                    ]),
            ])
            ->orderBy('class_roll')
            ->get(['id', 'student_id', 'class_roll']);
    }

    public function updatedExamId(): void
    {
        $this->resetAfter('exam');
        unset($this->classes, $this->exams);
    }

    public function updatedSchoolClassId(): void
    {
        $this->resetAfter('class');
        unset($this->sections, $this->subjects, $this->students);
    }

    public function updatedSectionId(): void
    {
        $this->marksData = [];
        unset($this->students);
        $this->loadMarksData();
    }

    public function updatedExamSubjectId(): void
    {
        $this->marksData = [];
        unset($this->paper, $this->students);
        $this->loadMarksData();
    }

    public function saveMarks(SaveMarks $saveMarks): void
    {
        Gate::authorize('academic.mark.enter');
        $this->ensureAccessibleSection();
        $paper = $this->paper;

        if ($paper === null || $paper->exam?->isLocked()) {
            throw ValidationException::withMessages([
                'examSubjectId' => __('Select an exam paper that is open for marks entry.'),
            ]);
        }

        $this->validate($this->rulesForGrid($paper));

        $enrollmentIds = $this->students->pluck('id')->sort()->values();
        $submittedIds = collect(array_keys($this->marksData))->map(fn (int|string $id): int => (int) $id)->sort()->values();

        if ($enrollmentIds->isEmpty() || $enrollmentIds->all() !== $submittedIds->all()) {
            throw ValidationException::withMessages([
                'marksData' => __('Enter marks for every student in the selected section.'),
            ]);
        }

        $components = $paper->activeComponents();
        $rows = [];

        foreach ($this->marksData as $enrollmentId => $row) {
            $rows[(int) $enrollmentId] = [
                'cq' => in_array('cq', $components, true) ? $row['written'] : null,
                'mcq' => in_array('mcq', $components, true) ? $row['mcq'] : null,
                'practical' => in_array('practical', $components, true) ? $row['practical'] : null,
                'total' => $components === [] ? $row['written'] : null,
                'is_absent' => $row['is_absent'],
            ];
        }

        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $result = $saveMarks->handle($paper, $rows, $user);

        unset($this->students);
        $this->loadMarksData();
        Flux::toast(
            variant: 'success',
            heading: __('Marks saved.'),
            text: trans_choice(':count result saved|:count results saved', $result['saved'], ['count' => $result['saved']]),
        );
    }

    /** @return array<string, mixed> */
    private function rulesForGrid(ExamSubject $paper): array
    {
        $rules = [
            'examId' => ['required', 'integer', Rule::exists(Exam::class, 'id')->where('is_locked', false)],
            'schoolClassId' => ['required', 'integer'],
            'sectionId' => ['required', 'integer'],
            'examSubjectId' => ['required', 'integer'],
            'marksData' => ['required', 'array', 'min:1'],
        ];
        $components = $paper->activeComponents();

        foreach ($this->students as $enrollment) {
            $id = $enrollment->id;
            $isPresent = fn (): bool => ! (bool) ($this->marksData[$id]['is_absent'] ?? false);
            $hasWritten = $components === [] || in_array('cq', $components, true);
            $writtenMax = in_array('cq', $components, true)
                ? (float) $paper->cq_full_marks
                : (float) $paper->full_marks;
            $rules["marksData.{$id}.is_absent"] = ['boolean'];
            $rules["marksData.{$id}.written"] = $hasWritten
                ? [new RequiredIf($isPresent), 'nullable', 'numeric', 'min:0', 'max:'.$writtenMax]
                : ['nullable'];
            $rules["marksData.{$id}.mcq"] = in_array('mcq', $components, true)
                ? [new RequiredIf($isPresent), 'nullable', 'numeric', 'min:0', 'max:'.(float) $paper->mcq_full_marks]
                : ['nullable'];
            $rules["marksData.{$id}.practical"] = in_array('practical', $components, true)
                ? [new RequiredIf($isPresent), 'nullable', 'numeric', 'min:0', 'max:'.(float) $paper->practical_full_marks]
                : ['nullable'];
        }

        return $rules;
    }

    private function loadMarksData(): void
    {
        if ($this->sectionId === null || $this->examSubjectId === null) {
            return;
        }

        $this->ensureAccessibleSection();
        $this->marksData = $this->students
            ->mapWithKeys(function (StudentEnrollment $enrollment): array {
                /** @var Mark|null $mark */
                $mark = $enrollment->marks->first();

                return [$enrollment->id => [
                    'written' => (string) ($mark?->cq_marks ?? ($mark?->obtained_marks ?? '')),
                    'mcq' => (string) ($mark?->mcq_marks ?? ''),
                    'practical' => (string) ($mark?->practical_marks ?? ''),
                    'is_absent' => $mark?->is_absent ?? false,
                    'total' => (string) ($mark?->obtained_marks ?? ''),
                    'grade' => $mark?->grade ?? '',
                    'gpa' => $mark?->gpa ?? '',
                ]];
            })
            ->all();
        $this->resetValidation();
    }

    private function resetAfter(string $filter): void
    {
        if ($filter === 'exam') {
            $this->schoolClassId = null;
        }

        $this->sectionId = null;
        $this->examSubjectId = null;
        $this->marksData = [];
    }

    private function ensureAccessibleSection(): Section
    {
        return $this->accessibleSectionsQuery()
            ->whereKey($this->sectionId)
            ->where('school_class_id', $this->schoolClassId)
            ->firstOrFail();
    }

    private function accessibleSectionsQuery(): Builder
    {
        return $this->constrainSectionAccess(Section::query()->where('is_active', true));
    }

    private function constrainSectionAccess(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user instanceof User && $user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Admin->value])) {
            return $query;
        }

        $sessionId = $this->examId !== null
            ? Exam::query()->whereKey($this->examId)->value('academic_session_id')
            : null;
        $employeeId = $user instanceof User ? $user->employee()->value('id') : null;

        if ($employeeId === null || $sessionId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('teacherAssignments', fn (Builder $assignment) => $assignment
            ->where('employee_id', $employeeId)
            ->where('academic_session_id', $sessionId));
    }
}

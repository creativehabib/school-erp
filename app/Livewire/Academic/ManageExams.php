<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Enums\ExamType;
use App\Models\Academic\AcademicSession;
use App\Models\Exam\Exam;
use App\Models\Exam\GradeScale;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Exam Management')]
class ManageExams extends Component
{
    public ?int $editingExamId = null;

    public ?int $academicSessionId = null;

    public ?int $gradeScaleId = null;

    public string $name = '';

    public string $nameBn = '';

    public string $code = '';

    public string $type = '';

    public ?int $term = null;

    public string $weight = '100.00';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $markEntryDeadline = '';

    public function mount(): void
    {
        Gate::authorize('academic.exam.view');
    }

    /** @return Collection<int, Exam> */
    #[Computed]
    public function exams(): Collection
    {
        return Exam::query()
            ->with(['academicSession:id,name', 'gradeScale:id,name'])
            ->withCount(['examSubjects', 'results'])
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, AcademicSession> */
    #[Computed]
    public function academicSessions(): Collection
    {
        return AcademicSession::query()->latest('year')->get(['id', 'name', 'year', 'is_locked']);
    }

    /** @return Collection<int, GradeScale> */
    #[Computed]
    public function gradeScales(): Collection
    {
        return GradeScale::query()->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'max_gpa']);
    }

    /** @return array<string, string> */
    public function examTypes(): array
    {
        return collect(ExamType::cases())->mapWithKeys(
            fn (ExamType $type): array => [$type->value => $type->label()]
        )->all();
    }

    public function create(): void
    {
        Gate::authorize('academic.exam.create');
        $this->resetForm();
        $this->academicSessionId = AcademicSession::query()->where('is_current', true)->value('id');
        $this->gradeScaleId = GradeScale::query()->where('is_default', true)->where('is_active', true)->value('id');
        Flux::modal('exam-form')->show();
    }

    public function edit(int $examId): void
    {
        Gate::authorize('academic.exam.update');
        $exam = Exam::query()->findOrFail($examId);

        abort_if($exam->isLocked(), 422, __('A locked exam cannot be edited.'));

        $this->editingExamId = $exam->id;
        $this->academicSessionId = $exam->academic_session_id;
        $this->gradeScaleId = $exam->grade_scale_id;
        $this->name = $exam->name;
        $this->nameBn = $exam->name_bn ?? '';
        $this->code = $exam->code;
        $this->type = $exam->type->value;
        $this->term = $exam->term;
        $this->weight = (string) $exam->weight;
        $this->startsOn = $exam->starts_on?->toDateString() ?? '';
        $this->endsOn = $exam->ends_on?->toDateString() ?? '';
        $this->markEntryDeadline = $exam->mark_entry_deadline?->toDateString() ?? '';
        $this->resetValidation();
        Flux::modal('exam-form')->show();
    }

    public function save(): void
    {
        Gate::authorize($this->editingExamId === null ? 'academic.exam.create' : 'academic.exam.update');
        $existingExam = $this->editingExamId === null ? null : Exam::query()->findOrFail($this->editingExamId);

        if ($existingExam?->isLocked()) {
            throw ValidationException::withMessages(['exam' => __('A locked exam cannot be edited.')]);
        }

        $validated = $this->validate([
            'academicSessionId' => ['required', 'integer', Rule::exists(AcademicSession::class, 'id')->where('is_locked', false)],
            'gradeScaleId' => ['required', 'integer', Rule::exists(GradeScale::class, 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:255'],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique(Exam::class, 'code')
                    ->where(fn (Builder $query) => $query->where('academic_session_id', $this->academicSessionId))
                    ->ignore($this->editingExamId),
            ],
            'type' => ['required', Rule::enum(ExamType::class)],
            'term' => ['nullable', 'integer', 'min:1', 'max:3'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'markEntryDeadline' => ['nullable', 'date'],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        Exam::query()->updateOrCreate(
            ['id' => $this->editingExamId],
            [
                'academic_session_id' => $validated['academicSessionId'],
                'grade_scale_id' => $validated['gradeScaleId'],
                'name' => $validated['name'],
                'name_bn' => filled($validated['nameBn']) ? $validated['nameBn'] : null,
                'code' => $validated['code'],
                'type' => $validated['type'],
                'term' => $validated['term'],
                'weight' => $validated['weight'],
                'starts_on' => filled($validated['startsOn']) ? $validated['startsOn'] : null,
                'ends_on' => filled($validated['endsOn']) ? $validated['endsOn'] : null,
                'mark_entry_deadline' => filled($validated['markEntryDeadline']) ? $validated['markEntryDeadline'] : null,
                'created_by' => $existingExam?->created_by ?? $user->id,
            ],
        );

        $message = $this->editingExamId === null ? __('Exam created.') : __('Exam updated.');
        unset($this->exams);
        $this->resetForm();
        Flux::modal('exam-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function delete(int $examId): void
    {
        Gate::authorize('academic.exam.delete');
        $exam = Exam::query()->withCount(['examSubjects', 'results'])->findOrFail($examId);

        if ($exam->isLocked() || $exam->exam_subjects_count > 0 || $exam->results_count > 0) {
            throw ValidationException::withMessages([
                'examDeletion' => __('A locked exam or an exam with papers or results cannot be deleted.'),
            ]);
        }

        $exam->delete();
        unset($this->exams);
        Flux::toast(variant: 'success', text: __('Exam deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingExamId', 'academicSessionId', 'gradeScaleId', 'name', 'nameBn',
            'code', 'type', 'term', 'startsOn', 'endsOn', 'markEntryDeadline',
        ]);
        $this->weight = '100.00';
        $this->resetValidation();
    }
}

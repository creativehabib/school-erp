<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Student ID Cards')]
class IDCardGenerator extends Component
{
    use WithPagination;

    public ?int $schoolClassId = null;

    public ?int $sectionId = null;

    /** @var array<int, int|string> */
    public array $selectedStudents = [];

    public bool $selectAll = false;

    public function mount(): void
    {
        Gate::authorize('document.id_card.generate');
    }

    /** @return Collection<int, SchoolClass> */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::query()->active()->ordered()->get(['id', 'name', 'level']);
    }

    /** @return Collection<int, Section> */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()
            ->active()
            ->when($this->schoolClassId, fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->when(! $this->schoolClassId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'school_class_id', 'name']);
    }

    #[Computed]
    public function students(): LengthAwarePaginator
    {
        return $this->filteredStudentsQuery()
            ->with([
                'currentEnrollment:id,student_id,school_class_id,section_id,class_roll',
                'currentEnrollment.schoolClass:id,name',
                'currentEnrollment.section:id,name',
            ])
            ->orderBy('name_en')
            ->paginate(15);
    }

    public function updatedSchoolClassId(): void
    {
        $this->sectionId = null;
        $this->resetSelection();
        unset($this->sections, $this->students);
        $this->resetPage();
    }

    public function updatedSectionId(): void
    {
        $this->resetSelection();
        unset($this->students);
        $this->resetPage();
    }

    public function updatedSelectAll(bool $selected): void
    {
        if (! $selected) {
            $this->selectedStudents = [];

            return;
        }

        $studentIds = $this->filteredStudentsQuery()->limit(101)->pluck('id');

        if ($studentIds->count() > 100) {
            $this->selectAll = false;
            throw ValidationException::withMessages([
                'selectedStudents' => __('Select a class and section containing no more than 100 students.'),
            ]);
        }

        $this->selectedStudents = $studentIds->all();
    }

    public function generate(): void
    {
        Gate::authorize('document.id_card.generate');

        $this->validate([
            'schoolClassId' => ['required', 'integer'],
            'sectionId' => ['required', 'integer'],
            'selectedStudents' => ['required', 'array', 'min:1', 'max:100'],
            'selectedStudents.*' => ['integer', 'distinct'],
        ]);

        $selectedIds = collect($this->selectedStudents)->map(fn (int|string $id): int => (int) $id)->unique()->values();
        $allowedIds = $this->filteredStudentsQuery()->whereKey($selectedIds)->pluck('id');

        if ($allowedIds->count() !== $selectedIds->count()) {
            throw ValidationException::withMessages([
                'selectedStudents' => __('One or more selected students are outside the chosen class and section.'),
            ]);
        }

        $url = URL::temporarySignedRoute(
            'admin.documents.id_cards.download',
            now()->addMinutes(5),
            ['ids' => $allowedIds->implode(',')],
        );

        $this->redirect($url);
    }

    private function filteredStudentsQuery(): Builder
    {
        return Student::query()
            ->active()
            ->when(
                $this->schoolClassId && $this->sectionId,
                fn (Builder $query) => $query->whereHas(
                    'currentEnrollment',
                    fn (Builder $enrollment) => $enrollment
                        ->where('school_class_id', $this->schoolClassId)
                        ->where('section_id', $this->sectionId),
                ),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    private function resetSelection(): void
    {
        $this->selectedStudents = [];
        $this->selectAll = false;
        $this->resetValidation('selectedStudents');
    }
}

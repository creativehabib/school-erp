<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Exceptions\ValidationException;

#[Layout('layouts.app')]
#[Title('Section Management')]
class ManageSections extends Component
{
    public ?int $editingSectionId = null;

    public ?int $schoolClassId = null;

    public ?int $shiftId = null;

    public string $name = '';

    public ?int $capacity = null;

    public string $roomNo = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('academic.section.view');
    }

    /** @return Collection<int, Section> */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()
            ->with(['schoolClass:id,name,level', 'shift:id,name'])
            ->withCount(['enrollments', 'teacherAssignments'])
            ->orderBy(
                SchoolClass::query()
                    ->select('level')
                    ->whereColumn('school_classes.id', 'sections.school_class_id')
            )
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, SchoolClass> */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::query()->active()->ordered()->get(['id', 'name', 'level']);
    }

    /** @return Collection<int, Shift> */
    #[Computed]
    public function shifts(): Collection
    {
        return Shift::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    public function create(): void
    {
        Gate::authorize('academic.section.create');
        $this->resetForm();
        Flux::modal('section-form')->show();
    }

    public function edit(int $sectionId): void
    {
        Gate::authorize('academic.section.update');
        $section = Section::query()->findOrFail($sectionId);

        $this->editingSectionId = $section->id;
        $this->schoolClassId = $section->school_class_id;
        $this->shiftId = $section->shift_id;
        $this->name = $section->name;
        $this->capacity = $section->capacity;
        $this->roomNo = $section->room_no ?? '';
        $this->isActive = $section->is_active;
        $this->resetValidation();
        Flux::modal('section-form')->show();
    }

    public function save(): void
    {
        Gate::authorize($this->editingSectionId === null ? 'academic.section.create' : 'academic.section.update');

        $validated = $this->validate([
            'schoolClassId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')->where('is_active', true)],
            'shiftId' => ['nullable', 'integer', Rule::exists(Shift::class, 'id')->where('is_active', true)],
            'name' => [
                'required',
                'string',
                'max:30',
                Rule::unique(Section::class, 'name')
                    ->where(fn (Builder $query) => $query
                        ->where('school_class_id', $this->schoolClassId)
                        ->when(
                            $this->shiftId === null,
                            fn (Builder $query) => $query->whereNull('shift_id'),
                            fn (Builder $query) => $query->where('shift_id', $this->shiftId),
                        ))
                    ->ignore($this->editingSectionId),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'roomNo' => ['nullable', 'string', 'max:20'],
            'isActive' => ['boolean'],
        ]);

        Section::query()->updateOrCreate(
            ['id' => $this->editingSectionId],
            [
                'school_class_id' => $validated['schoolClassId'],
                'shift_id' => $validated['shiftId'],
                'name' => $validated['name'],
                'capacity' => $validated['capacity'],
                'room_no' => filled($validated['roomNo']) ? $validated['roomNo'] : null,
                'is_active' => $validated['isActive'],
            ],
        );

        $message = $this->editingSectionId === null ? __('Section created.') : __('Section updated.');
        unset($this->sections);
        $this->resetForm();
        Flux::modal('section-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function delete(int $sectionId): void
    {
        Gate::authorize('academic.section.delete');
        $section = Section::query()->withCount(['enrollments', 'teacherAssignments'])->findOrFail($sectionId);

        if ($section->enrollments_count > 0 || $section->teacher_assignments_count > 0) {
            throw ValidationException::withMessages([
                'sectionDeletion' => __('A section with enrollments or teacher assignments cannot be deleted.'),
            ]);
        }

        $section->delete();
        unset($this->sections);
        Flux::toast(variant: 'success', text: __('Section deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingSectionId', 'schoolClassId', 'shiftId', 'name', 'capacity', 'roomNo']);
        $this->isActive = true;
        $this->resetValidation();
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Models\Academic\SchoolClass;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Exceptions\ValidationException;

#[Layout('layouts.app')]
#[Title('Class Management')]
class ManageClasses extends Component
{
    public ?int $editingClassId = null;

    public string $name = '';

    public string $nameBn = '';

    public string $code = '';

    public ?int $level = null;

    public bool $hasGroups = false;

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('academic.class.view');
    }

    /** @return Collection<int, SchoolClass> */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::query()
            ->withCount(['sections', 'enrollments'])
            ->ordered()
            ->get();
    }

    public function create(): void
    {
        Gate::authorize('academic.class.create');
        $this->resetForm();
        Flux::modal('class-form')->show();
    }

    public function edit(int $classId): void
    {
        Gate::authorize('academic.class.update');
        $schoolClass = SchoolClass::query()->findOrFail($classId);

        $this->editingClassId = $schoolClass->id;
        $this->name = $schoolClass->name;
        $this->nameBn = $schoolClass->name_bn ?? '';
        $this->code = $schoolClass->code ?? '';
        $this->level = $schoolClass->level;
        $this->hasGroups = $schoolClass->has_groups;
        $this->isActive = $schoolClass->is_active;
        $this->resetValidation();
        Flux::modal('class-form')->show();
    }

    public function save(): void
    {
        Gate::authorize($this->editingClassId === null ? 'academic.class.create' : 'academic.class.update');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique(SchoolClass::class, 'code')->ignore($this->editingClassId)],
            'level' => ['required', 'integer', 'min:1', 'max:99', Rule::unique(SchoolClass::class, 'level')->ignore($this->editingClassId)],
            'hasGroups' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        SchoolClass::query()->updateOrCreate(
            ['id' => $this->editingClassId],
            [
                'name' => $validated['name'],
                'name_bn' => filled($validated['nameBn']) ? $validated['nameBn'] : null,
                'code' => filled($validated['code']) ? $validated['code'] : null,
                'level' => $validated['level'],
                'has_groups' => $validated['hasGroups'],
                'is_active' => $validated['isActive'],
            ],
        );

        $message = $this->editingClassId === null ? __('Class created.') : __('Class updated.');
        unset($this->classes);
        $this->resetForm();
        Flux::modal('class-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function delete(int $classId): void
    {
        Gate::authorize('academic.class.delete');
        $schoolClass = SchoolClass::query()->withCount(['sections', 'enrollments'])->findOrFail($classId);

        if ($schoolClass->sections_count > 0 || $schoolClass->enrollments_count > 0) {
            throw ValidationException::withMessages([
                'classDeletion' => __('A class with sections or enrollments cannot be deleted.'),
            ]);
        }

        $schoolClass->delete();
        unset($this->classes);
        Flux::toast(variant: 'success', text: __('Class deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingClassId', 'name', 'nameBn', 'code', 'level', 'hasGroups']);
        $this->isActive = true;
        $this->resetValidation();
    }
}

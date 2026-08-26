<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Models\Academic\Shift;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Shift Management')]
class ManageShifts extends Component
{
    public ?int $editingShiftId = null;

    public string $name = '';

    public string $nameBn = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('academic.shift.view');
    }

    /** @return Collection<int, Shift> */
    #[Computed]
    public function shifts(): Collection
    {
        return Shift::query()
            ->withCount('sections')
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        Gate::authorize('academic.shift.create');
        $this->resetForm();
        Flux::modal('shift-form')->show();
    }

    public function edit(int $shiftId): void
    {
        Gate::authorize('academic.shift.update');
        $shift = Shift::query()->findOrFail($shiftId);

        $this->editingShiftId = $shift->id;
        $this->name = $shift->name;
        $this->nameBn = $shift->name_bn ?? '';
        $this->startsAt = $shift->starts_at ? Str::substr($shift->starts_at, 0, 5) : '';
        $this->endsAt = $shift->ends_at ? Str::substr($shift->ends_at, 0, 5) : '';
        $this->isActive = $shift->is_active;
        $this->resetValidation();
        Flux::modal('shift-form')->show();
    }

    public function save(): void
    {
        Gate::authorize($this->editingShiftId === null ? 'academic.shift.create' : 'academic.shift.update');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique(Shift::class, 'name')->ignore($this->editingShiftId)],
            'nameBn' => ['nullable', 'string', 'max:255'],
            'startsAt' => ['nullable', 'date_format:H:i'],
            'endsAt' => ['nullable', 'date_format:H:i', 'after:startsAt'],
            'isActive' => ['boolean'],
        ]);

        Shift::query()->updateOrCreate(
            ['id' => $this->editingShiftId],
            [
                'name' => $validated['name'],
                'name_bn' => filled($validated['nameBn']) ? $validated['nameBn'] : null,
                'starts_at' => filled($validated['startsAt']) ? $validated['startsAt'] : null,
                'ends_at' => filled($validated['endsAt']) ? $validated['endsAt'] : null,
                'is_active' => $validated['isActive'],
            ],
        );

        $message = $this->editingShiftId === null ? __('Shift created.') : __('Shift updated.');
        unset($this->shifts);
        $this->resetForm();
        Flux::modal('shift-form')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function delete(int $shiftId): void
    {
        Gate::authorize('academic.shift.delete');
        Shift::query()->findOrFail($shiftId)->delete();
        unset($this->shifts);
        Flux::toast(variant: 'success', text: __('Shift deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingShiftId', 'name', 'nameBn', 'startsAt', 'endsAt']);
        $this->isActive = true;
        $this->resetValidation();
    }
}

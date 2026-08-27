<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Actions\Academic\SaveAcademicSession;
use App\Models\Academic\AcademicSession;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Academic Years')]
class ManageAcademicYears extends Component
{
    public ?int $editingAcademicSessionId = null;

    public string $name = '';

    public ?int $year = null;

    public string $startsOn = '';

    public string $endsOn = '';

    public bool $isCurrent = false;

    public function mount(): void
    {
        Gate::authorize('academic.session.view');
    }

    /** @return Collection<int, AcademicSession> */
    #[Computed]
    public function academicYears(): Collection
    {
        return AcademicSession::query()
            ->withCount(['enrollments', 'exams'])
            ->latest('year')
            ->get();
    }

    public function create(): void
    {
        Gate::authorize('academic.session.create');
        $this->resetForm();
        $year = (int) now()->year;
        $this->name = (string) $year;
        $this->year = $year;
        $this->startsOn = "{$year}-01-01";
        $this->endsOn = "{$year}-12-31";
        $this->isCurrent = ! AcademicSession::query()->where('is_current', true)->exists();
        Flux::modal('academic-year-form')->show();
    }

    public function edit(int $academicSessionId): void
    {
        Gate::authorize('academic.session.update');
        $academicSession = AcademicSession::query()->findOrFail($academicSessionId);

        if ($academicSession->is_locked) {
            throw ValidationException::withMessages([
                'academicYear' => __('A locked academic year cannot be edited.'),
            ]);
        }

        $this->editingAcademicSessionId = $academicSession->id;
        $this->name = $academicSession->name;
        $this->year = $academicSession->year;
        $this->startsOn = $academicSession->starts_on->toDateString();
        $this->endsOn = $academicSession->ends_on->toDateString();
        $this->isCurrent = $academicSession->is_current;
        $this->resetValidation();
        Flux::modal('academic-year-form')->show();
    }

    public function save(SaveAcademicSession $saveAcademicSession): void
    {
        $academicSession = $this->editingAcademicSessionId === null
            ? null
            : AcademicSession::query()->findOrFail($this->editingAcademicSessionId);
        Gate::authorize($academicSession === null ? 'academic.session.create' : 'academic.session.update');

        if ($academicSession?->is_locked) {
            throw ValidationException::withMessages([
                'academicYear' => __('A locked academic year cannot be edited.'),
            ]);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique(AcademicSession::class, 'name')->ignore($academicSession)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique(AcademicSession::class, 'year')->ignore($academicSession)],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after:startsOn'],
            'isCurrent' => ['boolean'],
        ]);

        if ($academicSession?->is_current && ! $validated['isCurrent']) {
            throw ValidationException::withMessages([
                'isCurrent' => __('Make another academic year current before changing this one.'),
            ]);
        }

        $saveAcademicSession->handle($academicSession, [
            'name' => $validated['name'],
            'year' => $validated['year'],
            'starts_on' => $validated['startsOn'],
            'ends_on' => $validated['endsOn'],
            'is_current' => $validated['isCurrent'],
        ]);

        unset($this->academicYears);
        $this->resetForm();
        Flux::modal('academic-year-form')->close();
        Flux::toast(variant: 'success', text: __('Academic year saved.'));
    }

    public function makeCurrent(int $academicSessionId, SaveAcademicSession $saveAcademicSession): void
    {
        Gate::authorize('academic.session.update');
        $academicSession = AcademicSession::query()->findOrFail($academicSessionId);

        if ($academicSession->is_locked) {
            throw ValidationException::withMessages([
                'academicYear' => __('A locked academic year cannot be made current.'),
            ]);
        }

        $saveAcademicSession->handle($academicSession, [
            'name' => $academicSession->name,
            'year' => $academicSession->year,
            'starts_on' => $academicSession->starts_on->toDateString(),
            'ends_on' => $academicSession->ends_on->toDateString(),
            'is_current' => true,
        ]);
        unset($this->academicYears);
        Flux::toast(variant: 'success', text: __('Current academic year updated.'));
    }

    public function toggleLock(int $academicSessionId): void
    {
        Gate::authorize('academic.session.lock');
        $academicSession = AcademicSession::query()->findOrFail($academicSessionId);
        $academicSession->forceFill(['is_locked' => ! $academicSession->is_locked])->save();
        unset($this->academicYears);
        Flux::toast(variant: 'success', text: $academicSession->is_locked ? __('Academic year locked.') : __('Academic year unlocked.'));
    }

    public function delete(int $academicSessionId): void
    {
        Gate::authorize('academic.session.delete');
        $academicSession = AcademicSession::query()->withCount(['enrollments', 'exams'])->findOrFail($academicSessionId);

        if ($academicSession->is_current || $academicSession->enrollments_count > 0 || $academicSession->exams_count > 0) {
            throw ValidationException::withMessages([
                'academicYear' => __('A current or in-use academic year cannot be deleted.'),
            ]);
        }

        $academicSession->delete();
        unset($this->academicYears);
        Flux::toast(variant: 'success', text: __('Academic year deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingAcademicSessionId', 'name', 'year', 'startsOn', 'endsOn', 'isCurrent']);
        $this->resetValidation();
    }
}

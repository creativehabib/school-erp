<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Actions\Academic\AdmitStudent;
use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
#[Title('Student Admission')]
class StudentAdmission extends Component
{
    use WithFileUploads;

    public string $studentName = '';

    public string $studentEmail = '';

    public string $studentPhone = '';

    public string $dateOfBirth = '';

    public string $gender = '';

    public string $bloodGroup = '';

    public ?TemporaryUploadedFile $photo = null;

    public ?int $schoolClassId = null;

    public ?int $sectionId = null;

    public ?int $shiftId = null;

    public string $classRoll = '';

    public ?int $academicSessionId = null;

    public string $fatherName = '';

    public string $fatherPhone = '';

    public string $fatherEmail = '';

    public function mount(): void
    {
        Gate::authorize('academic.student.create');
        $this->academicSessionId = AcademicSession::query()
            ->where('is_current', true)
            ->where('is_locked', false)
            ->value('id');
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
        return Shift::query()->active()->orderBy('starts_at')->get(['id', 'name']);
    }

    /** @return Collection<int, AcademicSession> */
    #[Computed]
    public function academicSessions(): Collection
    {
        return AcademicSession::query()->where('is_locked', false)->latest('year')->get(['id', 'name', 'year']);
    }

    /** @return Collection<int, Section> */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()
            ->active()
            ->when($this->schoolClassId, fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->when($this->shiftId, fn (Builder $query) => $query->where('shift_id', $this->shiftId))
            ->when(! $this->schoolClassId || ! $this->shiftId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name', 'school_class_id', 'shift_id']);
    }

    public function updatedSchoolClassId(): void
    {
        $this->sectionId = null;
        unset($this->sections);
    }

    public function updatedShiftId(): void
    {
        $this->sectionId = null;
        unset($this->sections);
    }

    public function admit(AdmitStudent $admitStudent): void
    {
        Gate::authorize('academic.student.create');
        $validated = $this->validate($this->rules());
        $photoPath = null;

        if ($this->photo !== null) {
            $photoPath = $this->photo->store('students/photos', 'public');
        }

        try {
            $student = $admitStudent->handle([
                'student_name' => $validated['studentName'],
                'student_email' => $validated['studentEmail'],
                'student_phone' => filled($validated['studentPhone']) ? $validated['studentPhone'] : null,
                'date_of_birth' => $validated['dateOfBirth'],
                'gender' => $validated['gender'],
                'blood_group' => filled($validated['bloodGroup']) ? $validated['bloodGroup'] : null,
                'school_class_id' => $validated['schoolClassId'],
                'section_id' => $validated['sectionId'],
                'shift_id' => $validated['shiftId'],
                'class_roll' => $validated['classRoll'],
                'academic_session_id' => $validated['academicSessionId'],
                'father_name' => $validated['fatherName'],
                'father_phone' => $validated['fatherPhone'],
                'father_email' => filled($validated['fatherEmail']) ? $validated['fatherEmail'] : null,
            ], $photoPath);
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        $this->resetForm();
        Flux::toast(variant: 'success', heading: __('Student Admitted Successfully'), text: __('Admission number: :number', ['number' => $student->admission_no]));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'studentName' => ['required', 'string', 'max:255'],
            'studentEmail' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'studentPhone' => ['nullable', 'string', 'max:20', Rule::unique(User::class, 'phone')],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'bloodGroup' => ['nullable', Rule::enum(BloodGroup::class)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'schoolClassId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')->where('is_active', true)],
            'shiftId' => ['required', 'integer', Rule::exists(Shift::class, 'id')->where('is_active', true)],
            'sectionId' => [
                'required',
                'integer',
                Rule::exists(Section::class, 'id')
                    ->where('school_class_id', $this->schoolClassId)
                    ->where('shift_id', $this->shiftId)
                    ->where('is_active', true),
            ],
            'classRoll' => [
                'required',
                'string',
                'max:20',
                Rule::unique('student_enrollments', 'class_roll')
                    ->where('academic_session_id', $this->academicSessionId)
                    ->where('section_id', $this->sectionId),
            ],
            'academicSessionId' => [
                'required',
                'integer',
                Rule::exists(AcademicSession::class, 'id')->where('is_locked', false),
            ],
            'fatherName' => ['required', 'string', 'max:255'],
            'fatherPhone' => ['required', 'string', 'max:20'],
            'fatherEmail' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->academicSessionId = AcademicSession::query()
            ->where('is_current', true)
            ->where('is_locked', false)
            ->value('id');
        unset($this->sections);
        $this->resetValidation();
    }
}

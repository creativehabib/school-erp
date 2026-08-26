<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Daily Student Attendance') }}</flux:heading>
            <flux:text>{{ __('Record or update one attendance status for every student in the selected section.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="check-circle" wire:click="markAllPresent" :disabled="count($this->students) === 0">
            {{ __('Mark All as Present') }}
        </flux:button>
    </div>

    @if ($academicSessionId === null)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('No current academic year') }}">
            {{ __('Set an active academic year before recording attendance.') }}
        </flux:callout>
    @endif

    <flux:card class="space-y-5">
        <div class="grid gap-4 md:grid-cols-3">
            <flux:select wire:model.live="schoolClassId" :label="__('Class')" :disabled="$academicSessionId === null">
                <flux:select.option value="">{{ __('Select class') }}</flux:select.option>
                @foreach ($this->classes as $schoolClass)
                    <flux:select.option :value="$schoolClass->id">{{ $schoolClass->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="sectionId" :label="__('Section')" :disabled="! $schoolClassId">
                <flux:select.option value="">{{ $schoolClassId ? __('Select section') : __('Select class first') }}</flux:select.option>
                @foreach ($this->sections as $section)
                    <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live="date" :label="__('Attendance date')" type="date" :max="now()->toDateString()" required />
        </div>
    </flux:card>

    <flux:error name="attendanceData" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Roll No.') }}</flux:table.column>
                <flux:table.column>{{ __('Photo') }}</flux:table.column>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Attendance Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->students as $enrollment)
                    <flux:table.row :key="$enrollment->id">
                        <flux:table.cell variant="strong">{{ $enrollment->class_roll ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($enrollment->student?->photo_path)
                                <flux:avatar size="sm" :src="\Illuminate\Support\Facades\Storage::disk('public')->url($enrollment->student->photo_path)" :name="$enrollment->student->name_en" />
                            @else
                                <flux:avatar size="sm" :name="$enrollment->student?->name_en ?? __('Student')" />
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col gap-1">
                                <span class="font-medium">{{ $enrollment->student?->name_en }}</span>
                                <span class="text-xs text-zinc-500">{{ $enrollment->student?->admission_no }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:radio.group wire:model="attendanceData.{{ $enrollment->id }}" class="flex flex-wrap gap-x-4 gap-y-2">
                                <flux:radio value="present" :label="__('Present')" />
                                <flux:radio value="absent" :label="__('Absent')" />
                                <flux:radio value="late" :label="__('Late')" />
                                <flux:radio value="leave" :label="__('Leave')" />
                            </flux:radio.group>
                            <flux:error name="attendanceData.{{ $enrollment->id }}" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <div class="py-10 text-center text-zinc-500">
                                {{ $sectionId ? __('No currently enrolled students were found.') : __('Select a class and section to load students.') }}
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:text>{{ trans_choice(':count student loaded|:count students loaded', count($this->students), ['count' => count($this->students)]) }}</flux:text>
        <flux:button variant="primary" icon="check" wire:click="saveAttendance" wire:loading.attr="disabled" wire:target="saveAttendance" :disabled="count($this->students) === 0">
            <span wire:loading.remove wire:target="saveAttendance">{{ __('Save Attendance') }}</span>
            <span wire:loading wire:target="saveAttendance">{{ __('Saving…') }}</span>
        </flux:button>
    </div>
</section>

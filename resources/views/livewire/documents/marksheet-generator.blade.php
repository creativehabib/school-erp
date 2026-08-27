<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Marksheet Generator') }}</flux:heading>
            <flux:text>{{ __('Generate individual or section-wide progress reports from processed exam results.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="arrow-down-tray" wire:click="generateBulk" wire:loading.attr="disabled" wire:target="generateBulk" :disabled="count($this->students) === 0">
            {{ __('Bulk generate') }}
        </flux:button>
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-3">
            <flux:select wire:model.live="examId" :label="__('Exam')">
                <flux:select.option value="">{{ __('Select exam') }}</flux:select.option>
                @foreach ($this->exams as $exam)
                    <flux:select.option wire:key="marksheet-exam-{{ $exam->id }}" :value="$exam->id">{{ $exam->name }} — {{ $exam->academicSession?->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="schoolClassId" :label="__('Class')" :disabled="! $examId">
                <flux:select.option value="">{{ $examId ? __('Select class') : __('Select exam first') }}</flux:select.option>
                @foreach ($this->classes as $schoolClass)
                    <flux:select.option wire:key="marksheet-class-{{ $schoolClass->id }}" :value="$schoolClass->id">{{ $schoolClass->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="sectionId" :label="__('Section')" :disabled="! $schoolClassId">
                <flux:select.option value="">{{ $schoolClassId ? __('Select section') : __('Select class first') }}</flux:select.option>
                @foreach ($this->sections as $section)
                    <flux:select.option wire:key="marksheet-section-{{ $section->id }}" :value="$section->id">{{ $section->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <flux:error name="marksheets" />

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Roll') }}</flux:table.column>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('GPA') }}</flux:table.column>
                <flux:table.column>{{ __('Final grade') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Marksheet') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->students as $enrollment)
                    @php($result = $enrollment->examResults->first())
                    <flux:table.row :key="$enrollment->id">
                        <flux:table.cell variant="strong">{{ $enrollment->class_roll ?: '—' }}</flux:table.cell>
                        <flux:table.cell><div class="space-y-1"><div class="font-medium">{{ $enrollment->student?->name_en }}</div><div class="text-xs text-zinc-500">{{ $enrollment->student?->admission_no }}</div></div></flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $result?->gpa, 2) }}</flux:table.cell>
                        <flux:table.cell><flux:badge :color="$result?->is_failed ? 'red' : 'lime'">{{ $result?->grade ?? '—' }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" variant="ghost" icon="document-arrow-down" wire:click="generate({{ $result?->id }})" wire:loading.attr="disabled" wire:target="generate">{{ __('Generate') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5"><div class="py-10 text-center text-zinc-500">{{ $sectionId ? __('No processed results were found for this section.') : __('Select an exam, class, and section to load results.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>

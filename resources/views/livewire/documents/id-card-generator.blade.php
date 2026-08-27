<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Student ID Cards') }}</flux:heading>
            <flux:text>{{ __('Filter the current enrollment, select students, and generate a print-ready A4 PDF.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="document-arrow-down" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" :disabled="count($selectedStudents) === 0">
            {{ __('Generate ID Cards') }}
        </flux:button>
    </div>

    <flux:card class="space-y-5">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:select wire:model.live="schoolClassId" :label="__('Class')">
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
        </div>
    </flux:card>

    <flux:error name="selectedStudents" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->students">
            <flux:table.columns>
                <flux:table.column>
                    <flux:checkbox wire:model.live="selectAll" :disabled="! $sectionId" :aria-label="__('Select all filtered students')" />
                </flux:table.column>
                <flux:table.column>{{ __('Admission no.') }}</flux:table.column>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Section') }}</flux:table.column>
                <flux:table.column>{{ __('Roll') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->students as $student)
                    <flux:table.row :key="$student->id">
                        <flux:table.cell><flux:checkbox wire:model.live="selectedStudents" :value="$student->id" :aria-label="__('Select :name', ['name' => $student->name_en])" /></flux:table.cell>
                        <flux:table.cell>{{ $student->admission_no }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $student->name_en }}</flux:table.cell>
                        <flux:table.cell>{{ $student->currentEnrollment?->schoolClass?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $student->currentEnrollment?->section?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $student->currentEnrollment?->class_roll ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6"><div class="py-10 text-center text-zinc-500">{{ $sectionId ? __('No active students were found in this section.') : __('Select a class and section to list students.') }}</div></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:text>{{ trans_choice(':count student selected|:count students selected', count($selectedStudents), ['count' => count($selectedStudents)]) }}</flux:text>
        <flux:button variant="primary" icon="document-arrow-down" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" :disabled="count($selectedStudents) === 0">
            <span wire:loading.remove wire:target="generate">{{ __('Generate ID Cards') }}</span>
            <span wire:loading wire:target="generate">{{ __('Preparing PDF…') }}</span>
        </flux:button>
    </div>
</section>

<section class="w-full space-y-6">
    <div class="space-y-1">
        <flux:heading size="xl">{{ __('Exam Marks Entry') }}</flux:heading>
        <flux:text>{{ __('Enter component marks for a complete section. Totals, grades, and GPA are calculated automatically.') }}</flux:text>
    </div>

    <flux:card class="space-y-5">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="examId" :label="__('Exam')">
                <flux:select.option value="">{{ __('Select exam') }}</flux:select.option>
                @foreach ($this->exams as $exam)
                    <flux:select.option :value="$exam->id">{{ $exam->name }} — {{ $exam->academicSession?->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="schoolClassId" :label="__('Class')" :disabled="! $examId">
                <flux:select.option value="">{{ $examId ? __('Select class') : __('Select exam first') }}</flux:select.option>
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
            <flux:select wire:model.live="examSubjectId" :label="__('Subject')" :disabled="! $schoolClassId">
                <flux:select.option value="">{{ $schoolClassId ? __('Select subject') : __('Select class first') }}</flux:select.option>
                @foreach ($this->subjects as $examSubject)
                    <flux:select.option :value="$examSubject->id">
                        {{ $examSubject->classSubject?->subject?->name }} ({{ (float) $examSubject->full_marks }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <flux:error name="marksData" />
    <flux:error name="examSubjectId" />

    @php
        $paper = $this->paper;
        $components = $paper?->activeComponents() ?? [];
        $showWritten = $paper && ($components === [] || in_array('cq', $components, true));
        $showMcq = $paper && in_array('mcq', $components, true);
        $showPractical = $paper && in_array('practical', $components, true);
    @endphp

    @if ($paper)
        <div class="flex flex-wrap gap-2">
            <flux:badge color="indigo">{{ __('Full marks: :marks', ['marks' => (float) $paper->full_marks]) }}</flux:badge>
            <flux:badge color="zinc">{{ __('Pass marks: :marks', ['marks' => (float) $paper->pass_marks]) }}</flux:badge>
            @if ($showWritten)<flux:badge color="zinc">{{ __('Written: :marks', ['marks' => (float) ($paper->cq_full_marks ?? $paper->full_marks)]) }}</flux:badge>@endif
            @if ($showMcq)<flux:badge color="zinc">{{ __('MCQ: :marks', ['marks' => (float) $paper->mcq_full_marks]) }}</flux:badge>@endif
            @if ($showPractical)<flux:badge color="zinc">{{ __('Practical: :marks', ['marks' => (float) $paper->practical_full_marks]) }}</flux:badge>@endif
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Roll') }}</flux:table.column>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                @if ($showWritten)<flux:table.column>{{ __('Written') }}</flux:table.column>@endif
                @if ($showMcq)<flux:table.column>{{ __('MCQ') }}</flux:table.column>@endif
                @if ($showPractical)<flux:table.column>{{ __('Practical') }}</flux:table.column>@endif
                <flux:table.column>{{ __('Absent') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Grade / GPA') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->students as $enrollment)
                    <flux:table.row :key="$enrollment->id">
                        <flux:table.cell variant="strong">{{ $enrollment->class_roll ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="min-w-40"><div class="font-medium">{{ $enrollment->student?->name_en }}</div><div class="text-xs text-zinc-500">{{ $enrollment->student?->admission_no }}</div></div>
                        </flux:table.cell>
                        @if ($showWritten)
                            <flux:table.cell><div class="w-28"><flux:input wire:model.blur="marksData.{{ $enrollment->id }}.written" type="number" min="0" :max="$paper->cq_full_marks ?? $paper->full_marks" step="0.01" size="sm" :disabled="$marksData[$enrollment->id]['is_absent'] ?? false" /><flux:error name="marksData.{{ $enrollment->id }}.written" /></div></flux:table.cell>
                        @endif
                        @if ($showMcq)
                            <flux:table.cell><div class="w-24"><flux:input wire:model.blur="marksData.{{ $enrollment->id }}.mcq" type="number" min="0" :max="$paper->mcq_full_marks" step="0.01" size="sm" :disabled="$marksData[$enrollment->id]['is_absent'] ?? false" /><flux:error name="marksData.{{ $enrollment->id }}.mcq" /></div></flux:table.cell>
                        @endif
                        @if ($showPractical)
                            <flux:table.cell><div class="w-24"><flux:input wire:model.blur="marksData.{{ $enrollment->id }}.practical" type="number" min="0" :max="$paper->practical_full_marks" step="0.01" size="sm" :disabled="$marksData[$enrollment->id]['is_absent'] ?? false" /><flux:error name="marksData.{{ $enrollment->id }}.practical" /></div></flux:table.cell>
                        @endif
                        <flux:table.cell><flux:checkbox wire:model.live="marksData.{{ $enrollment->id }}.is_absent" :aria-label="__('Mark :name absent', ['name' => $enrollment->student?->name_en])" /></flux:table.cell>
                        <flux:table.cell>{{ $marksData[$enrollment->id]['total'] !== '' ? (float) $marksData[$enrollment->id]['total'] : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if (($marksData[$enrollment->id]['grade'] ?? '') !== '')
                                <div class="flex items-center gap-2"><flux:badge :color="$marksData[$enrollment->id]['grade'] === 'F' ? 'red' : 'lime'">{{ $marksData[$enrollment->id]['grade'] }}</flux:badge><span>{{ number_format((float) $marksData[$enrollment->id]['gpa'], 2) }}</span></div>
                            @else
                                <span class="text-zinc-500">{{ __('Not saved') }}</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="8"><div class="py-10 text-center text-zinc-500">{{ $paper && $sectionId ? __('No currently enrolled students were found.') : __('Select an exam, class, section, and subject to load students.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="flex justify-end">
        <flux:button variant="primary" icon="check" wire:click="saveMarks" wire:loading.attr="disabled" wire:target="saveMarks" :disabled="count($this->students) === 0">
            <span wire:loading.remove wire:target="saveMarks">{{ __('Save Marks') }}</span>
            <span wire:loading wire:target="saveMarks">{{ __('Saving…') }}</span>
        </flux:button>
    </div>
</section>

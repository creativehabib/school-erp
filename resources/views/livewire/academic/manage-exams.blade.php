<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Exam Management') }}</flux:heading>
            <flux:text>{{ __('Create exam events, configure result weight, and control the marks-entry period.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add exam') }}</flux:button>
    </div>

    <flux:error name="exam" />
    <flux:error name="examDeletion" />

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Exam') }}</flux:table.column>
                <flux:table.column>{{ __('Academic year') }}</flux:table.column>
                <flux:table.column>{{ __('Type / Term') }}</flux:table.column>
                <flux:table.column>{{ __('Schedule') }}</flux:table.column>
                <flux:table.column>{{ __('Weight') }}</flux:table.column>
                <flux:table.column>{{ __('Papers / Results') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->exams as $exam)
                    <flux:table.row :key="$exam->id">
                        <flux:table.cell variant="strong"><div class="space-y-1"><div>{{ $exam->name }}</div><div class="text-xs font-normal text-zinc-500">{{ $exam->code }}</div></div></flux:table.cell>
                        <flux:table.cell>{{ $exam->academicSession?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $exam->type->label() }}{{ $exam->term ? ' / '.$exam->term : '' }}</flux:table.cell>
                        <flux:table.cell>{{ $exam->starts_on?->format('d M Y') ?? '—' }} — {{ $exam->ends_on?->format('d M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $exam->weight, 2) }}%</flux:table.cell>
                        <flux:table.cell>{{ $exam->exam_subjects_count }} / {{ $exam->results_count }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$exam->is_locked ? 'red' : 'lime'">{{ $exam->is_locked ? __('Locked') : __('Open') }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $exam->id }})" :disabled="$exam->is_locked" :aria-label="__('Edit :name', ['name' => $exam->name])" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $exam->id }})" wire:confirm="{{ __('Delete this exam?') }}" :disabled="$exam->is_locked" :aria-label="__('Delete :name', ['name' => $exam->name])" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="8"><div class="py-10 text-center text-zinc-500">{{ __('No exams have been configured.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="exam-form" class="md:w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-1"><flux:heading size="lg">{{ $editingExamId ? __('Edit exam') : __('Add exam') }}</flux:heading><flux:text>{{ __('Create the exam first, then configure its class papers for marks entry.') }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="academicSessionId" :label="__('Academic year')" required>
                    <flux:select.option value="">{{ __('Select academic year') }}</flux:select.option>
                    @foreach ($this->academicSessions as $session)
                        <flux:select.option wire:key="exam-session-{{ $session->id }}" :value="$session->id" :disabled="$session->is_locked">{{ $session->name }}{{ $session->is_locked ? ' — '.__('Locked') : '' }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="gradeScaleId" :label="__('Grade scale')" required>
                    <flux:select.option value="">{{ __('Select grade scale') }}</flux:select.option>
                    @foreach ($this->gradeScales as $scale)
                        <flux:select.option wire:key="exam-scale-{{ $scale->id }}" :value="$scale->id">{{ $scale->name }} ({{ number_format((float) $scale->max_gpa, 2) }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('Exam name')" required autofocus />
                <flux:input wire:model="nameBn" :label="__('Bangla name')" />
                <flux:input wire:model="code" :label="__('Code')" placeholder="ANNUAL-2026" required />
                <flux:select wire:model="type" :label="__('Exam type')" required>
                    <flux:select.option value="">{{ __('Select type') }}</flux:select.option>
                    @foreach ($this->examTypes() as $value => $label)
                        <flux:select.option wire:key="exam-type-{{ $value }}" :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="term" :label="__('Term number')" type="number" min="1" max="3" />
                <flux:input wire:model="weight" :label="__('Result weight (%)')" type="number" min="0" max="100" step="0.01" required />
                <flux:input wire:model="startsOn" :label="__('Starts on')" type="date" />
                <flux:input wire:model="endsOn" :label="__('Ends on')" type="date" />
                <div class="sm:col-span-2"><flux:input wire:model="markEntryDeadline" :label="__('Marks-entry deadline')" type="date" /></div>
            </div>
            <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save exam') }}</flux:button></div>
        </form>
    </flux:modal>
</section>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Academic Years') }}</flux:heading>
            <flux:text>{{ __('Create academic years and choose the one used by attendance, admission, exams, and fees.') }}</flux:text>
        </div>
        @can('academic.session.create')
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Create academic year') }}</flux:button>
        @endcan
    </div>

    <flux:error name="academicYear" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Academic year') }}</flux:table.column>
                <flux:table.column>{{ __('Date range') }}</flux:table.column>
                <flux:table.column>{{ __('Usage') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->academicYears as $academicYear)
                    <flux:table.row :key="$academicYear->id">
                        <flux:table.cell variant="strong"><div class="flex flex-col gap-1"><span>{{ $academicYear->name }}</span><span class="text-xs text-zinc-500">{{ $academicYear->year }}</span></div></flux:table.cell>
                        <flux:table.cell>{{ $academicYear->starts_on->format('d M Y') }} – {{ $academicYear->ends_on->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ trans_choice(':count enrollment|:count enrollments', $academicYear->enrollments_count, ['count' => $academicYear->enrollments_count]) }} · {{ trans_choice(':count exam|:count exams', $academicYear->exams_count, ['count' => $academicYear->exams_count]) }}</flux:table.cell>
                        <flux:table.cell><div class="flex flex-wrap gap-1">@if ($academicYear->is_current)<flux:badge color="lime">{{ __('Current') }}</flux:badge>@endif @if ($academicYear->is_locked)<flux:badge color="red">{{ __('Locked') }}</flux:badge>@else<flux:badge color="zinc">{{ __('Open') }}</flux:badge>@endif</div></flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if (! $academicYear->is_current && ! $academicYear->is_locked)
                                    @can('academic.session.update')<flux:button size="sm" variant="ghost" wire:click="makeCurrent({{ $academicYear->id }})">{{ __('Make current') }}</flux:button>@endcan
                                @endif
                                @if (! $academicYear->is_locked)
                                    @can('academic.session.update')<flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $academicYear->id }})" :aria-label="__('Edit :name', ['name' => $academicYear->name])" />@endcan
                                @endif
                                @can('academic.session.lock')<flux:button size="sm" variant="ghost" :icon="$academicYear->is_locked ? 'lock-open' : 'lock-closed'" wire:click="toggleLock({{ $academicYear->id }})" :aria-label="$academicYear->is_locked ? __('Unlock :name', ['name' => $academicYear->name]) : __('Lock :name', ['name' => $academicYear->name])" />@endcan
                                @if (! $academicYear->is_current && $academicYear->enrollments_count === 0 && $academicYear->exams_count === 0)
                                    @can('academic.session.delete')<flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $academicYear->id }})" wire:confirm="{{ __('Delete this academic year?') }}" :aria-label="__('Delete :name', ['name' => $academicYear->name])" />@endcan
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5"><div class="py-10 text-center text-zinc-500">{{ __('No academic year exists. Create one to start attendance and admissions.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="academic-year-form" class="md:w-lg">
        <form wire:submit="save" class="space-y-6">
            <div><flux:heading size="lg">{{ $editingAcademicSessionId ? __('Edit academic year') : __('Create academic year') }}</flux:heading><flux:text class="mt-1">{{ __('Only one academic year can be current at a time.') }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" placeholder="2026" required />
                <flux:input wire:model="year" :label="__('Year')" type="number" min="2000" max="2100" required />
                <flux:input wire:model="startsOn" :label="__('Starts on')" type="date" required />
                <flux:input wire:model="endsOn" :label="__('Ends on')" type="date" required />
                <flux:switch wire:model="isCurrent" :label="__('Use as current academic year')" class="sm:col-span-2" />
            </div>
            <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button type="submit" variant="primary">{{ __('Save academic year') }}</flux:button></div>
        </form>
    </flux:modal>
</section>

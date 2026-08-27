<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Section Management') }}</flux:heading>
            <flux:text>{{ __('Assign sections to classes and optionally organize them by shift, room, and capacity.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add section') }}</flux:button>
    </div>

    <flux:error name="sectionDeletion" />

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Section') }}</flux:table.column>
                <flux:table.column>{{ __('Shift') }}</flux:table.column>
                <flux:table.column>{{ __('Room') }}</flux:table.column>
                <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                <flux:table.column>{{ __('Students / Teachers') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->sections as $section)
                    <flux:table.row :key="$section->id">
                        <flux:table.cell variant="strong">{{ $section->schoolClass?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $section->name }}</flux:table.cell>
                        <flux:table.cell>{{ $section->shift?->name ?? __('Not assigned') }}</flux:table.cell>
                        <flux:table.cell>{{ $section->room_no ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $section->capacity ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $section->enrollments_count }} / {{ $section->teacher_assignments_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$section->is_active ? 'lime' : 'zinc'">
                                {{ $section->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $section->id }})" :aria-label="__('Edit section :name', ['name' => $section->name])" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $section->id }})" wire:confirm="{{ __('Delete this section?') }}" :aria-label="__('Delete section :name', ['name' => $section->name])" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8"><div class="py-10 text-center text-zinc-500">{{ __('No sections have been configured.') }}</div></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="section-form" class="md:w-xl">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-1">
                <flux:heading size="lg">{{ $editingSectionId ? __('Edit section') : __('Add section') }}</flux:heading>
                <flux:text>{{ __('A section name must be unique within its class and shift.') }}</flux:text>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="schoolClassId" :label="__('Class')" required>
                    <flux:select.option value="">{{ __('Select class') }}</flux:select.option>
                    @foreach ($this->classes as $schoolClass)
                        <flux:select.option wire:key="section-class-{{ $schoolClass->id }}" :value="$schoolClass->id">{{ $schoolClass->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="shiftId" :label="__('Shift')">
                    <flux:select.option value="">{{ __('No shift') }}</flux:select.option>
                    @foreach ($this->shifts as $shift)
                        <flux:select.option wire:key="section-shift-{{ $shift->id }}" :value="$shift->id">{{ $shift->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" :label="__('Section name')" placeholder="A" required autofocus />
                <flux:input wire:model="roomNo" :label="__('Room number')" />
                <flux:input wire:model="capacity" :label="__('Capacity')" type="number" min="1" max="500" />
                <flux:switch wire:model="isActive" :label="__('Active')" />
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save section') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

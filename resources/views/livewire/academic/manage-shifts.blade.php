<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Shift Management') }}</flux:heading>
            <flux:text>{{ __('Configure the operating times used by academic sections.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add shift') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Shift') }}</flux:table.column>
                <flux:table.column>{{ __('Schedule') }}</flux:table.column>
                <flux:table.column>{{ __('Sections') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->shifts as $shift)
                    <flux:table.row :key="$shift->id">
                        <flux:table.cell variant="strong">
                            <div class="flex flex-col gap-1"><span>{{ $shift->name }}</span><span class="text-sm font-normal text-zinc-500">{{ $shift->name_bn ?: '—' }}</span></div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $shift->starts_at && $shift->ends_at ? "{$shift->starts_at} – {$shift->ends_at}" : __('Not specified') }}</flux:table.cell>
                        <flux:table.cell>{{ $shift->sections_count }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$shift->is_active ? 'lime' : 'zinc'">{{ $shift->is_active ? __('Active') : __('Inactive') }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $shift->id }})" :aria-label="__('Edit :name', ['name' => $shift->name])" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $shift->id }})" wire:confirm="{{ __('Delete this shift? Existing sections will become unassigned.') }}" :aria-label="__('Delete :name', ['name' => $shift->name])" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5"><div class="py-10 text-center text-zinc-500">{{ __('No shifts have been configured.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="shift-form" class="md:w-lg">
        <form wire:submit="save" class="space-y-6">
            <div><flux:heading size="lg">{{ $editingShiftId ? __('Edit shift') : __('Add shift') }}</flux:heading><flux:text class="mt-1">{{ __('Enter the shift name and optional operating hours.') }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" required autofocus />
                <flux:input wire:model="nameBn" :label="__('Bangla name')" />
                <flux:input wire:model="startsAt" :label="__('Starts at')" type="time" />
                <flux:input wire:model="endsAt" :label="__('Ends at')" type="time" />
                <flux:switch wire:model="isActive" :label="__('Active')" class="sm:col-span-2" />
            </div>
            <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button type="submit" variant="primary">{{ __('Save shift') }}</flux:button></div>
        </form>
    </flux:modal>
</section>

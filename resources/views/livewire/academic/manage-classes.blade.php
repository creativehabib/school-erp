<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Class Management') }}</flux:heading>
            <flux:text>{{ __('Manage class levels, codes, group availability, and status.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add class') }}</flux:button>
    </div>

    <flux:error name="classDeletion" />

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Level') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Sections / Students') }}</flux:table.column>
                <flux:table.column>{{ __('Groups') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->classes as $schoolClass)
                    <flux:table.row :key="$schoolClass->id">
                        <flux:table.cell>{{ $schoolClass->level }}</flux:table.cell>
                        <flux:table.cell variant="strong"><div class="flex flex-col gap-1"><span>{{ $schoolClass->name }}</span><span class="text-sm font-normal text-zinc-500">{{ $schoolClass->name_bn ?: '—' }}</span></div></flux:table.cell>
                        <flux:table.cell>{{ $schoolClass->code ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $schoolClass->sections_count }} / {{ $schoolClass->enrollments_count }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$schoolClass->has_groups ? 'indigo' : 'zinc'">{{ $schoolClass->has_groups ? __('Enabled') : __('Not used') }}</flux:badge></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$schoolClass->is_active ? 'lime' : 'zinc'">{{ $schoolClass->is_active ? __('Active') : __('Inactive') }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $schoolClass->id }})" :aria-label="__('Edit :name', ['name' => $schoolClass->name])" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $schoolClass->id }})" wire:confirm="{{ __('Delete this class?') }}" :aria-label="__('Delete :name', ['name' => $schoolClass->name])" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="7"><div class="py-10 text-center text-zinc-500">{{ __('No classes have been configured.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="class-form" class="md:w-xl">
        <form wire:submit="save" class="space-y-6">
            <div><flux:heading size="lg">{{ $editingClassId ? __('Edit class') : __('Add class') }}</flux:heading><flux:text class="mt-1">{{ __('The numeric level determines promotion order.') }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" required autofocus />
                <flux:input wire:model="nameBn" :label="__('Bangla name')" />
                <flux:input wire:model="code" :label="__('Code')" />
                <flux:input wire:model="level" :label="__('Numeric level')" type="number" min="1" max="99" required />
                <flux:switch wire:model="hasGroups" :label="__('Has academic groups')" />
                <flux:switch wire:model="isActive" :label="__('Active')" />
            </div>
            <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button type="submit" variant="primary">{{ __('Save class') }}</flux:button></div>
        </form>
    </flux:modal>
</section>

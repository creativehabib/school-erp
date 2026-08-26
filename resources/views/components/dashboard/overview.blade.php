@props([
    'eyebrow',
    'heading',
    'description',
    'metrics' => [],
    'actions' => [],
])

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:badge color="indigo" size="sm" class="w-fit">{{ $eyebrow }}</flux:badge>
        <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
        <flux:text class="max-w-3xl">{{ $description }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metrics as $metric)
            <flux:card wire:key="metric-{{ $loop->index }}" class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <flux:text>{{ $metric['label'] }}</flux:text>
                    <flux:icon :name="$metric['icon']" class="size-5 text-zinc-400" />
                </div>
                <flux:heading size="xl">{{ $metric['value'] }}</flux:heading>
                <flux:text size="sm">{{ $metric['detail'] }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    <flux:card class="flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg">{{ __('Quick actions') }}</flux:heading>
            <flux:text>{{ __('The module workspaces will become active as each module is implemented.') }}</flux:text>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($actions as $action)
                <div wire:key="action-{{ $loop->index }}" class="flex items-center gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300">
                        <flux:icon :name="$action['icon']" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="sm">{{ $action['label'] }}</flux:heading>
                        <flux:text size="sm" class="truncate">{{ $action['description'] }}</flux:text>
                    </div>
                </div>
            @endforeach
        </div>
    </flux:card>
</div>

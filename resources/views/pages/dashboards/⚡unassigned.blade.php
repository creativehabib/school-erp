<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard access pending')] class extends Component {};
?>

<div class="mx-auto flex w-full max-w-2xl flex-col gap-6 py-12">
    <flux:callout icon="information-circle" variant="warning">
        <flux:callout.heading>{{ __('Dashboard access pending') }}</flux:callout.heading>
        <flux:callout.text>{{ __('Your account is active but does not have an ERP role yet. Contact an administrator to complete your access setup.') }}</flux:callout.text>
    </flux:callout>

    <div>
        <flux:button :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
            {{ __('Open profile settings') }}
        </flux:button>
    </div>
</div>

@props(['user'])

@php
    $dashboardRoute = $user->dashboardRoute();
    $role = collect(\App\Enums\RoleName::cases())->first(fn ($role) => $user->hasRole($role->value));
@endphp

<flux:sidebar.group :heading="__('Workspace')" class="grid">
    <flux:sidebar.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>
    @can('viewAny', \App\Models\User::class)
        <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
            {{ __('Users & access') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.shift.view')
        <flux:sidebar.item icon="clock" :href="route('admin.academic.shifts')" :current="request()->routeIs('admin.academic.shifts')" wire:navigate>
            {{ __('Shifts') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.class.view')
        <flux:sidebar.item icon="academic-cap" :href="route('admin.academic.classes')" :current="request()->routeIs('admin.academic.classes')" wire:navigate>
            {{ __('Classes') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.student.create')
        <flux:sidebar.item icon="user-plus" :href="route('admin.students.admit')" :current="request()->routeIs('admin.students.admit')" wire:navigate>
            {{ __('Student admission') }}
        </flux:sidebar.item>
    @endcan
    <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*', 'appearance.*', 'security.*')" wire:navigate>
        {{ __('Account settings') }}
    </flux:sidebar.item>
</flux:sidebar.group>

@if ($role)
    <div class="px-3 py-2">
        <flux:badge color="indigo" size="sm">{{ $role->label() }}</flux:badge>
    </div>
@endif

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
    @can('academic.session.view')
        <flux:sidebar.item icon="calendar-days" :href="route('admin.academic.years')" :current="request()->routeIs('admin.academic.years')" wire:navigate>
            {{ __('Academic years') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.class.view')
        <flux:sidebar.item icon="academic-cap" :href="route('admin.academic.classes')" :current="request()->routeIs('admin.academic.classes')" wire:navigate>
            {{ __('Classes') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.section.view')
        <flux:sidebar.item icon="rectangle-group" :href="route('admin.academic.sections')" :current="request()->routeIs('admin.academic.sections')" wire:navigate>
            {{ __('Sections') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.exam.create')
        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.academic.exams')" :current="request()->routeIs('admin.academic.exams')" wire:navigate>
            {{ __('Exams') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.student.create')
        <flux:sidebar.item icon="user-plus" :href="route('admin.students.admit')" :current="request()->routeIs('admin.students.admit')" wire:navigate>
            {{ __('Student admission') }}
        </flux:sidebar.item>
    @endcan
    @can('document.id_card.generate')
        <flux:sidebar.item icon="identification" :href="route('admin.documents.id_cards.index')" :current="request()->routeIs('admin.documents.id_cards.*')" wire:navigate>
            {{ __('Student ID cards') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.attendance.record')
        <flux:sidebar.item icon="clipboard-document-check" :href="route('attendance.take')" :current="request()->routeIs('attendance.take')" wire:navigate>
            {{ __('Take attendance') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.mark.enter')
        <flux:sidebar.item icon="pencil-square" :href="route('exams.marks')" :current="request()->routeIs('exams.marks')" wire:navigate>
            {{ __('Marks entry') }}
        </flux:sidebar.item>
    @endcan
    @can('academic.marksheet.generate')
        <flux:sidebar.item icon="document-chart-bar" :href="route('documents.marksheets.index')" :current="request()->routeIs('documents.marksheets.*')" wire:navigate>
            {{ __('Marksheets') }}
        </flux:sidebar.item>
    @endcan
    <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*', 'appearance.*', 'security.*')" wire:navigate>
        {{ __('Account settings') }}
    </flux:sidebar.item>
</flux:sidebar.group>

@if ($user->hasAnyRole([\App\Enums\RoleName::SuperAdmin->value, \App\Enums\RoleName::Admin->value, \App\Enums\RoleName::Teacher->value]))
    <flux:sidebar.group :heading="__('HRM')" expandable>
        @can('hrm.employee.view')
            @if (! $user->hasRole(\App\Enums\RoleName::Teacher->value))
                <flux:sidebar.item icon="user-group" :href="route('hrm.admin.staff')" :current="request()->routeIs('hrm.admin.staff')" wire:navigate>{{ __('Staff Directory') }}</flux:sidebar.item>
            @endif
        @endcan
        @if ($user->hasAnyRole([\App\Enums\RoleName::SuperAdmin->value, \App\Enums\RoleName::Admin->value]))
            <flux:sidebar.item icon="check-circle" :href="route('hrm.admin.leave_approvals')" :current="request()->routeIs('hrm.admin.leave_approvals')" wire:navigate>{{ __('Leave Approvals') }}</flux:sidebar.item>
            <flux:sidebar.item icon="banknotes" :href="route('hrm.admin.payroll')" :current="request()->routeIs('hrm.admin.payroll')" wire:navigate>{{ __('Payroll & Salary') }}</flux:sidebar.item>
        @else
            <flux:sidebar.item icon="calendar-days" :href="route('hrm.self.leaves')" :current="request()->routeIs('hrm.self.leaves')" wire:navigate>{{ __('My Leaves') }}</flux:sidebar.item>
            <flux:sidebar.item icon="document-currency-dollar" :href="route('hrm.self.payslips')" :current="request()->routeIs('hrm.self.payslips*')" wire:navigate>{{ __('My Payslips') }}</flux:sidebar.item>
        @endif
    </flux:sidebar.group>
@endif

@if ($user->hasAnyRole([\App\Enums\RoleName::SuperAdmin->value, \App\Enums\RoleName::Admin->value, \App\Enums\RoleName::Teacher->value, \App\Enums\RoleName::Student->value]))
    <flux:sidebar.group :heading="__('Library')" expandable>
        @if ($user->hasAnyRole([\App\Enums\RoleName::SuperAdmin->value, \App\Enums\RoleName::Admin->value]))
            <flux:sidebar.item icon="book-open" :href="route('library.admin.books')" :current="request()->routeIs('library.admin.books')" wire:navigate>{{ __('Manage Books') }}</flux:sidebar.item>
            <flux:sidebar.item icon="arrows-right-left" :href="route('library.admin.issues')" :current="request()->routeIs('library.admin.issues')" wire:navigate>{{ __('Issue / Return') }}</flux:sidebar.item>
        @else
            <flux:sidebar.item icon="book-open" :href="route('library.my_books')" :current="request()->routeIs('library.my_books')" wire:navigate>{{ __('My Books') }}</flux:sidebar.item>
        @endif
    </flux:sidebar.group>
@endif

@if ($role)
    <div class="px-3 py-2">
        <flux:badge color="indigo" size="sm">{{ $role->label() }}</flux:badge>
    </div>
@endif

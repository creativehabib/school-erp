<?php

use App\Actions\System\SaveUserAccess;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users & Access')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = '';
    public ?int $editingUserId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $status = 'active';
    public string $password = '';
    public string $password_confirmation = '';

    /** @var array<int, string> */
    public array $roles = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->select(['id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->with('roles:id,name')
            ->search($this->search)
            ->when($this->roleFilter !== '', fn ($query) => $query->role($this->roleFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);
    }

    public function create(): void
    {
        Gate::authorize('create', User::class);
        $this->resetForm();
        Flux::modal('user-form')->show();
    }

    public function edit(int $userId): void
    {
        $user = User::query()->with('roles:id,name')->findOrFail($userId);
        Gate::authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';
        $this->status = $user->status->value;
        $this->roles = $user->roles->pluck('name')->all();
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetValidation();
        Flux::modal('user-form')->show();
    }

    public function save(SaveUserAccess $saveUserAccess): void
    {
        $user = $this->editingUserId === null ? null : User::query()->findOrFail($this->editingUserId);
        Gate::authorize($user === null ? 'create' : 'update', $user ?? User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique(User::class)->ignore($user)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                Rule::enum(RoleName::class),
                Rule::notIn(auth()->user()->isSuperAdmin() ? [] : [RoleName::SuperAdmin->value]),
            ],
        ]);

        $saveUserAccess->handle($user, $validated, $validated['roles']);
        unset($this->users);
        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: $user === null ? __('User created.') : __('User updated.'));
        $this->resetForm();
    }

    public function delete(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        Gate::authorize('delete', $user);
        $user->delete();
        unset($this->users);
        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingUserId', 'name', 'email', 'phone', 'password', 'password_confirmation', 'roles']);
        $this->status = UserStatus::Active->value;
        $this->resetValidation();
    }
};
?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">{{ __('Users & Access') }}</flux:heading>
            <flux:text>{{ __('Create login accounts, control account status, and assign system roles.') }}</flux:text>
        </div>
        @can('create', \App\Models\User::class)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add user') }}</flux:button>
        @endcan
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email, or phone')" />
        <flux:select wire:model.live="roleFilter">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach (RoleName::cases() as $role)
                <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFilter">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (UserStatus::cases() as $userStatus)
                <flux:select.option :value="$userStatus->value">{{ $userStatus->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Roles') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell><div class="flex flex-col gap-1 text-sm"><span>{{ $user->email ?: '—' }}</span><span class="text-zinc-500">{{ $user->phone ?: '—' }}</span></div></flux:table.cell>
                        <flux:table.cell><div class="flex flex-wrap gap-1">@foreach ($user->roles as $role)<flux:badge size="sm">{{ RoleName::tryFrom($role->name)?->label() ?? $role->name }}</flux:badge>@endforeach</div></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$user->status->color()">{{ $user->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @can('update', $user)<flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $user->id }})" :aria-label="__('Edit :name', ['name' => $user->name])" />@endcan
                                @can('delete', $user)<flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $user->id }})" wire:confirm="{{ __('Delete this user? This action cannot be undone.') }}" :aria-label="__('Delete :name', ['name' => $user->name])" />@endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5"><div class="py-8 text-center text-zinc-500">{{ __('No users match these filters.') }}</div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="user-form" class="md:w-xl">
        <form wire:submit="save" class="space-y-6">
            <div><flux:heading size="lg">{{ $editingUserId ? __('Edit user') : __('Add user') }}</flux:heading><flux:text class="mt-1">{{ __('Every account must have at least one role.') }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" required class="sm:col-span-2" />
                <flux:input wire:model="email" :label="__('Email')" type="email" />
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:select wire:model="status" :label="__('Status')">@foreach (UserStatus::cases() as $userStatus)<flux:select.option :value="$userStatus->value">{{ $userStatus->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:checkbox.group wire:model="roles" :label="__('Roles')" class="sm:col-span-2"><div class="grid gap-3 sm:grid-cols-2">@foreach (RoleName::cases() as $role) @if ($role !== RoleName::SuperAdmin || auth()->user()->isSuperAdmin()) <flux:checkbox :value="$role->value" :label="$role->label()" /> @endif @endforeach</div></flux:checkbox.group>
                <flux:error name="roles" />
                <flux:input wire:model="password" :label="$editingUserId ? __('New password (optional)') : __('Password')" type="password" :required="$editingUserId === null" />
                <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" :required="$editingUserId === null" />
            </div>
            <div class="flex justify-end gap-3"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button type="submit" variant="primary">{{ __('Save user') }}</flux:button></div>
        </form>
    </flux:modal>
</section>

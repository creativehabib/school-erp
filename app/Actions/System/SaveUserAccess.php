<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveUserAccess
{
    /**
     * @param  array{name: string, email: string|null, phone: string|null, status: string, password?: string|null}  $attributes
     * @param  array<int, string>  $roles
     */
    public function handle(?User $user, array $attributes, array $roles): User
    {
        return DB::transaction(function () use ($user, $attributes, $roles): User {
            $user ??= new User;
            $attributes['email'] = filled($attributes['email']) ? $attributes['email'] : null;
            $attributes['phone'] = filled($attributes['phone']) ? $attributes['phone'] : null;
            $user->fill(Arr::except($attributes, ['password', 'roles']));

            if (filled($attributes['password'] ?? null)) {
                $user->password = $attributes['password'];
            }

            $user->status = UserStatus::from($attributes['status']);
            $user->save();
            $user->syncRoles(array_map(
                static fn (string $role): string => RoleName::from($role)->value,
                $roles,
            ));

            return $user->refresh();
        });
    }
}

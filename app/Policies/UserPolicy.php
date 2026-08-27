<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('system.user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('system.user.create');
    }

    public function update(User $user, User $subject): bool
    {
        return $user->can('system.user.update')
            && (! $subject->isSuperAdmin() || $user->isSuperAdmin());
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->isNot($subject)
            && ! $subject->isSuperAdmin()
            && $user->can('system.user.delete');
    }
}

<?php

namespace App\Core\Users\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function invite(User $user): bool
    {
        return $user->can('users.invite');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update');
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->can('users.deactivate') && $target->isNot($user);
    }
}

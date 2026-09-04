<?php

namespace App\Core\Users\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Defense in depth: even though User already carries a TenantScope
     * global scope, every authorization check re-verifies the tenant match
     * explicitly so a bypassed/forgotten scope can never leak cross-tenant data.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $target->tenant_id === $user->tenant_id && $user->can('users.view');
    }

    public function invite(User $user): bool
    {
        return $user->can('users.invite');
    }

    public function update(User $user, User $target): bool
    {
        return $target->tenant_id === $user->tenant_id && $user->can('users.update');
    }

    public function deactivate(User $user, User $target): bool
    {
        return $target->tenant_id === $user->tenant_id
            && $user->can('users.deactivate')
            && $target->isNot($user);
    }
}

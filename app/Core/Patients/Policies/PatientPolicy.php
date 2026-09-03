<?php

namespace App\Core\Patients\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Defense in depth: even though Patient already carries a TenantScope
     * global scope, every authorization check re-verifies the tenant match
     * explicitly so a bypassed/forgotten scope can never leak cross-tenant data.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && $user->can('patients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('patients.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && $user->can('patients.update');
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && $user->can('patients.delete');
    }
}

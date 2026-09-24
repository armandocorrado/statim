<?php

namespace App\Core\Patients\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('patients.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can('patients.update');
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('patients.delete');
    }
}

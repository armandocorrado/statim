<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Models\DentalAlert;
use App\Modules\Dental\Support\ClinicalAccessChecker;

class DentalAlertPolicy
{
    public function view(User $user, DentalAlert $alert): bool
    {
        return ClinicalAccessChecker::canView($user);
    }

    public function update(User $user, DentalAlert $alert): bool
    {
        return ClinicalAccessChecker::canManage($user);
    }

    public function createFor(User $user, Patient $patient): bool
    {
        return ClinicalAccessChecker::canManage($user);
    }
}

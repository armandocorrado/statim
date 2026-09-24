<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Models\DentalAnamnesis;
use App\Modules\Dental\Support\ClinicalAccessChecker;

class DentalAnamnesisPolicy
{
    public function view(User $user, DentalAnamnesis $anamnesis): bool
    {
        return ClinicalAccessChecker::canView($user);
    }

    public function update(User $user, DentalAnamnesis $anamnesis): bool
    {
        return ClinicalAccessChecker::canManage($user);
    }

    /**
     * Per la creazione (nessuna riga esiste ancora) si autorizza contro
     * il Patient di destinazione, stesso pattern di
     * AppointmentPolicy::create($user, $operatorId).
     */
    public function createFor(User $user, Patient $patient): bool
    {
        return ClinicalAccessChecker::canManage($user);
    }
}

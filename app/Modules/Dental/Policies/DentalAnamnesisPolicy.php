<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Models\DentalAnamnesis;
use App\Modules\Dental\Support\ClinicalAccessChecker;

class DentalAnamnesisPolicy
{
    /**
     * Defense in depth: ri-verifica sempre il tenant match esplicitamente,
     * indipendentemente dalla global scope — stesso principio già usato
     * nel resto del progetto.
     */
    public function view(User $user, DentalAnamnesis $anamnesis): bool
    {
        return $anamnesis->tenant_id === $user->tenant_id && ClinicalAccessChecker::canView($user);
    }

    public function update(User $user, DentalAnamnesis $anamnesis): bool
    {
        return $anamnesis->tenant_id === $user->tenant_id && ClinicalAccessChecker::canManage($user);
    }

    /**
     * Per la creazione (nessuna riga esiste ancora) si autorizza contro
     * il Patient di destinazione, stesso pattern di
     * AppointmentPolicy::create($user, $operatorId).
     */
    public function createFor(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && ClinicalAccessChecker::canManage($user);
    }
}

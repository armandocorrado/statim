<?php

namespace App\Core\Agenda\Policies;

use App\Core\Agenda\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Defense in depth: ri-verifica sempre il tenant match esplicitamente,
     * indipendentemente dalla global scope — stesso principio di PatientPolicy.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('agenda.view.own') || $user->can('agenda.view.all');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($appointment->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->can('agenda.view.all')
            || ($user->can('agenda.view.own') && $appointment->operator_id === $user->id);
    }

    /**
     * $operatorId è l'operatore a cui si vuole assegnare il nuovo
     * appuntamento — chi ha solo "own" può assegnarlo solo a se stesso.
     */
    public function create(User $user, string $operatorId): bool
    {
        return $user->can('agenda.manage.all')
            || ($user->can('agenda.manage.own') && $operatorId === $user->id);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if ($appointment->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->can('agenda.manage.all')
            || ($user->can('agenda.manage.own') && $appointment->operator_id === $user->id);
    }
}

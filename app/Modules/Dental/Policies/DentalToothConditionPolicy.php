<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Models\DentalToothCondition;

/**
 * A differenza delle altre risorse cliniche, l'odontogramma non usa
 * ClinicalAccessChecker (pieno OR igiene): oggi solo admin/odontoiatra
 * hanno odontogram.view/update nel catalogo RBAC — l'igienista non vi
 * accede affatto, per decisione esplicita (competenza igienica non
 * comprende la diagnosi/stato degli elementi dentali). Se in futuro
 * l'igienista dovesse ottenere un accesso parziale, passa da qui.
 */
class DentalToothConditionPolicy
{
    public function view(User $user, DentalToothCondition $condition): bool
    {
        return $user->can('odontogram.view');
    }

    public function viewOdontogram(User $user, Patient $patient): bool
    {
        return $user->can('odontogram.view');
    }

    public function createFor(User $user, Patient $patient): bool
    {
        return $user->can('odontogram.update');
    }
}

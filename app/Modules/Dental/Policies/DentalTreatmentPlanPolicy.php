<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Support\TreatmentPlanAccessChecker;

class DentalTreatmentPlanPolicy
{
    public function viewAny(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && $user->can('treatment_plans.view');
    }

    public function view(User $user, DentalTreatmentPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id && $user->can('treatment_plans.view');
    }

    public function createFor(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && TreatmentPlanAccessChecker::canManageAny($user);
    }

    public function update(User $user, DentalTreatmentPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id && TreatmentPlanAccessChecker::canManageAny($user);
    }

    public function delete(User $user, DentalTreatmentPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    /**
     * Amministrativo, non clinico — segreteria/admin generano il
     * preventivo dal piano, i ruoli clinici non gestiscono il ciclo di
     * vita commerciale.
     */
    public function generateQuote(User $user, DentalTreatmentPlan $plan): bool
    {
        return $plan->tenant_id === $user->tenant_id && $user->can('treatment_plans.administer');
    }
}

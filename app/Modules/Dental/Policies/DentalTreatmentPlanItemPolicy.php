<?php

namespace App\Modules\Dental\Policies;

use App\Models\User;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;
use App\Modules\Dental\Support\TreatmentPlanAccessChecker;

/**
 * create/update restano al controllo "grezzo" (ha un permesso qualunque
 * di gestione piano?) — il controllo fine sulla categoria della voce
 * SOTTOMESSA (quella che risulterà dopo il salvataggio) avviene nella
 * FormRequest, dove il service_catalog_item_id scelto è già noto. delete
 * non ha un valore "sottomesso" da controllare: qui il controllo fine
 * avviene direttamente sulla categoria della voce ESISTENTE.
 */
class DentalTreatmentPlanItemPolicy
{
    public function createFor(User $user, DentalTreatmentPlan $plan): bool
    {
        return TreatmentPlanAccessChecker::canManageAny($user);
    }

    public function update(User $user, DentalTreatmentPlanItem $item): bool
    {
        return TreatmentPlanAccessChecker::canManageAny($user);
    }

    public function delete(User $user, DentalTreatmentPlanItem $item): bool
    {
        return TreatmentPlanAccessChecker::canManageItem($user, $item->serviceCatalogItem->category);
    }
}

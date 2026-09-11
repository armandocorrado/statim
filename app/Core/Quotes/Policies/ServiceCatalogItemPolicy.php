<?php

namespace App\Core\Quotes\Policies;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Models\User;

/**
 * Chi definisce/gestisce i prezzi (segreteria/admin) usa
 * treatment_plans.administer; chi costruisce un piano di cura deve poter
 * SFOGLIARE il listino per scegliere le voci, quindi viewAny è più largo
 * — include anche i permessi clinici del verticale. Sono controllati come
 * semplici stringhe di permesso (spatie), non importando nulla da
 * App\Modules\Dental: nessun confine violato, solo un valore dato.
 */
class ServiceCatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('treatment_plans.view')
            || $user->can('treatment_plans.administer')
            || $user->can('treatment_plans.clinical.manage')
            || $user->can('treatment_plans.hygiene.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('treatment_plans.administer');
    }

    public function update(User $user, ServiceCatalogItem $item): bool
    {
        return $item->tenant_id === $user->tenant_id && $user->can('treatment_plans.administer');
    }
}

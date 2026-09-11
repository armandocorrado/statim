<?php

namespace Database\Factories;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalTreatmentPlanItem>
 */
class DentalTreatmentPlanItemFactory extends Factory
{
    protected $model = DentalTreatmentPlanItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'treatment_plan_id' => DentalTreatmentPlan::factory(),
            // Creato esplicitamente sotto lo stesso tenant_id (mai lasciato
            // a un ServiceCatalogItem::factory() indipendente): altrimenti,
            // quando un test passa tenant_id esplicitamente sovrascrivendo
            // il default, la voce di catalogo risulterebbe di un tenant
            // diverso — invisibile tramite la relazione serviceCatalogItem()
            // (filtrata dalla global scope di BelongsToTenant), con
            // DentalTreatmentPlanItemPolicy che troverebbe null anziché la
            // categoria attesa.
            'service_catalog_item_id' => fn (array $attributes) => ServiceCatalogItem::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->id,
            'quantity' => 1,
            'notes' => null,
            'session_group' => null,
            'sort_order' => 0,
        ];
    }
}

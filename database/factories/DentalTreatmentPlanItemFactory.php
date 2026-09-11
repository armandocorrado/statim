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
            'service_catalog_item_id' => ServiceCatalogItem::factory(),
            'quantity' => 1,
            'notes' => null,
            'session_group' => null,
            'sort_order' => 0,
        ];
    }
}

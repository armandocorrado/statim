<?php

namespace Database\Factories;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;
use App\Modules\Dental\Models\DentalTreatmentPlanItemTooth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalTreatmentPlanItemTooth>
 */
class DentalTreatmentPlanItemToothFactory extends Factory
{
    protected $model = DentalTreatmentPlanItemTooth::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'treatment_plan_item_id' => DentalTreatmentPlanItem::factory(),
            'tooth_number' => '16',
        ];
    }
}

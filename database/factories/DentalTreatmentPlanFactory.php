<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalTreatmentPlan>
 */
class DentalTreatmentPlanFactory extends Factory
{
    protected $model = DentalTreatmentPlan::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'created_by' => User::factory(),
            'title' => 'Piano conservativo',
            'notes' => null,
        ];
    }
}

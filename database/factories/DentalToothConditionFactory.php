<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Dental\Enums\ToothCondition;
use App\Modules\Dental\Models\DentalToothCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalToothCondition>
 */
class DentalToothConditionFactory extends Factory
{
    protected $model = DentalToothCondition::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'operator_id' => User::factory(),
            'tooth_number' => '11',
            'condition_type' => ToothCondition::Carious,
            'recorded_date' => now()->toDateString(),
            'diary_entry_id' => null,
            'notes' => null,
        ];
    }
}

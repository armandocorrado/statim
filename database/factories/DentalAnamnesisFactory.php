<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Dental\Models\DentalAnamnesis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalAnamnesis>
 */
class DentalAnamnesisFactory extends Factory
{
    protected $model = DentalAnamnesis::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'pathologies' => null,
            'medications' => null,
            'risk_factors' => null,
            'notes' => null,
            'updated_by' => User::factory(),
        ];
    }
}

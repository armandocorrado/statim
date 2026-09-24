<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Dental\Enums\DentalAlertCategory;
use App\Modules\Dental\Models\DentalAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalAlert>
 */
class DentalAlertFactory extends Factory
{
    protected $model = DentalAlert::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'category' => DentalAlertCategory::Allergy,
            'description' => 'Allergia alla penicillina',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}

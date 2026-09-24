<?php

namespace Database\Factories;

use App\Core\Patients\Enums\PatientSource;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'gender' => fake()->randomElement(['M', 'F']),
            'birth_place' => fake()->city(),
            'fiscal_code' => null,
            'email' => fake()->safeEmail(),
            'mobile_phone' => fake()->phoneNumber(),
            'landline_phone' => null,
            'address_street' => fake()->streetAddress(),
            'address_postal_code' => fake()->numerify('#####'),
            'address_city' => fake()->city(),
            'address_province' => strtoupper(fake()->lexify('??')),
            'residence_street' => null,
            'residence_postal_code' => null,
            'residence_city' => null,
            'residence_province' => null,
            'vat_number' => null,
            'source' => fake()->randomElement(PatientSource::cases()),
            'guardian_patient_id' => null,
            'guardian_relationship' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Core\Consents\Enums\ConsentCollectionMethod;
use App\Core\Consents\Enums\ConsentPurpose;
use App\Core\Consents\Enums\PolicyVersion;
use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'given_by_patient_id' => null,
            'purpose' => ConsentPurpose::Cura,
            'collection_method' => ConsentCollectionMethod::Cartaceo,
            'policy_version' => PolicyVersion::V1,
            'granted_at' => now(),
            'revoked_at' => null,
            'recorded_by' => User::factory(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}

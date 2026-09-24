<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDiaryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalDiaryEntry>
 */
class DentalDiaryEntryFactory extends Factory
{
    protected $model = DentalDiaryEntry::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'operator_id' => User::factory(),
            'entry_date' => now()->toDateString(),
            'section' => DentalRecordSection::General,
            'content' => fake()->sentence(10),
        ];
    }

    public function hygiene(): static
    {
        return $this->state(fn () => ['section' => DentalRecordSection::Hygiene]);
    }
}

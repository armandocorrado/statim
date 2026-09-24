<?php

namespace Database\Factories;

use App\Core\Agenda\Models\AppointmentType;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentType>
 */
class AppointmentTypeFactory extends Factory
{
    protected $model = AppointmentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => '#2563eb',
            'is_active' => true,
        ];
    }
}

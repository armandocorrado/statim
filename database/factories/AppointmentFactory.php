<?php

namespace Database\Factories;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+2 weeks');
        $end = (clone $start)->modify('+30 minutes');

        return [
            'patient_id' => Patient::factory(),
            'operator_id' => User::factory(),
            'assistant_id' => null,
            'appointment_type_id' => null,
            'start_at' => $start,
            'end_at' => $end,
            'status' => AppointmentStatus::Scheduled,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => AppointmentStatus::Cancelled]);
    }
}

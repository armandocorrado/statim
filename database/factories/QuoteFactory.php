<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Models\Quote;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'source_treatment_plan_id' => null,
            'status' => QuoteStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::Issued,
            'issued_at' => now()->toDateString(),
            'total_taxable' => 100.00,
            'total_vat' => 0,
            'total_amount' => 100.00,
        ]);
    }

    public function accepted(): static
    {
        return $this->issued()->state(fn () => [
            'status' => QuoteStatus::Accepted,
            'responded_at' => now()->toDateString(),
        ]);
    }

    public function rejected(): static
    {
        return $this->issued()->state(fn () => [
            'status' => QuoteStatus::Rejected,
            'responded_at' => now()->toDateString(),
        ]);
    }
}

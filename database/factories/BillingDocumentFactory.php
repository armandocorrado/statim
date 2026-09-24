<?php

namespace Database\Factories;

use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingDocument>
 */
class BillingDocumentFactory extends Factory
{
    protected $model = BillingDocument::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'recipient_patient_id' => null,
            'recipient_name' => fake()->name(),
            'recipient_fiscal_code' => null,
            'recipient_vat_number' => null,
            'recipient_address_street' => fake()->streetAddress(),
            'recipient_address_postal_code' => fake()->numerify('#####'),
            'recipient_address_city' => fake()->city(),
            'recipient_address_province' => strtoupper(fake()->lexify('??')),
            'status' => BillingDocumentStatus::Draft,
            'document_number' => null,
            'document_year' => null,
            'issued_at' => null,
            'fiscal_channel' => null,
            'external_reference' => null,
            'total_taxable' => null,
            'total_vat' => null,
            'total_amount' => null,
            'created_by' => User::factory(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => BillingDocumentStatus::Issued,
            'document_number' => fake()->unique()->numberBetween(1, 9999),
            'document_year' => (int) now()->format('Y'),
            'issued_at' => now(),
            'fiscal_channel' => \App\Core\Billing\Enums\FiscalChannel::SistemaTs,
            'total_taxable' => 100,
            'total_vat' => 0,
            'total_amount' => 100,
        ]);
    }
}

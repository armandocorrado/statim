<?php

namespace Database\Factories;

use App\Core\Billing\Models\BillingDocument;
use App\Core\Billing\Models\BillingDocumentLine;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingDocumentLine>
 */
class BillingDocumentLineFactory extends Factory
{
    protected $model = BillingDocumentLine::class;

    public function definition(): array
    {
        $quantity = 1;
        $unitPrice = fake()->randomFloat(2, 20, 200);

        return [
            'billing_document_id' => BillingDocument::factory(),
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate' => null,
            'vat_exemption_reason' => 'Art. 10 n. 18 DPR 633/72 - prestazione sanitaria',
            'line_total' => round($quantity * $unitPrice, 2),
            'sort_order' => 0,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\QuoteLine;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    protected $model = QuoteLine::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quote_id' => Quote::factory(),
            'service_catalog_item_id' => null,
            'source_treatment_plan_item_id' => null,
            'description' => 'Otturazione — dente 16',
            'quantity' => 1,
            'unit_price' => 80.00,
            'discount_percent' => null,
            'vat_rate' => null,
            'vat_exemption_reason' => 'art. 10 n. 18 DPR 633/72',
            'line_total' => 80.00,
            'sort_order' => 0,
        ];
    }
}

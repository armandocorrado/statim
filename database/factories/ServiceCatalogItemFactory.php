<?php

namespace Database\Factories;

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCatalogItem>
 */
class ServiceCatalogItemFactory extends Factory
{
    protected $model = ServiceCatalogItem::class;

    public function definition(): array
    {
        return [
            'name' => 'Otturazione',
            'description' => null,
            'category' => 'general',
            'base_price' => 80.00,
            'default_vat_rate' => null,
            'default_vat_exemption_reason' => 'art. 10 n. 18 DPR 633/72',
            'default_duration_minutes' => 30,
            'is_active' => true,
        ];
    }

    public function hygiene(): static
    {
        return $this->state(fn () => ['name' => 'Igiene orale', 'category' => 'hygiene']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

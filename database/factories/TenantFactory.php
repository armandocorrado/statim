<?php

namespace Database\Factories;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = 'Studio '.fake()->unique()->lastName();

        $slug = Str::slug($name).'-'.fake()->unique()->numerify('###');

        return [
            'name' => $name,
            'slug' => $slug,
            'database_name' => 'medcare_tenant_'.str_replace('-', '_', $slug),
            'vat_number' => fake()->numerify('IT###########'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}

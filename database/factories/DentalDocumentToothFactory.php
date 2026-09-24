<?php

namespace Database\Factories;

use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Models\DentalDocumentTooth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalDocumentTooth>
 */
class DentalDocumentToothFactory extends Factory
{
    protected $model = DentalDocumentTooth::class;

    public function definition(): array
    {
        return [
            'document_id' => DentalDocument::factory(),
            'tooth_number' => '11',
        ];
    }
}

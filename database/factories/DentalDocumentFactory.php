<?php

namespace Database\Factories;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DentalDocument>
 */
class DentalDocumentFactory extends Factory
{
    protected $model = DentalDocument::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'section' => DentalRecordSection::General,
            'document_type' => 'referto',
            'description' => 'Referto di controllo',
            'file_path' => 'dental-documents/fake/fake.pdf',
            'original_filename' => 'referto.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12345,
            'uploaded_by' => User::factory(),
        ];
    }

    public function hygiene(): static
    {
        return $this->state(fn () => ['section' => DentalRecordSection::Hygiene]);
    }
}

<?php

namespace App\Modules\Dental\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\DentalAnamnesisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un record per paziente, mutabile — la storicità la dà l'audit log
 * (trait Auditable) sul diff, non righe multiple. Non sezionata: riguarda
 * chiunque tratti il paziente, non solo chi fa igiene — vedi
 * DentalRecordSection.
 */
#[Fillable(['patient_id', 'pathologies', 'medications', 'risk_factors', 'notes'])]
class DentalAnamnesis extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalAnamnesisFactory::new();
    }

    protected function casts(): array
    {
        return [
            'pathologies' => 'encrypted',
            'medications' => 'encrypted',
            'risk_factors' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

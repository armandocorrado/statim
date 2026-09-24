<?php

namespace App\Modules\Dental\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\UsesTenantConnection;
use App\Models\User;
use App\Modules\Dental\Enums\DentalAlertCategory;
use Database\Factories\DentalAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabella separata (non testo libero dentro l'anamnesi) apposta per
 * essere queryabile e mostrabile in modo prominente. Non sezionata: un
 * alert (allergia, fattore di rischio) riguarda chiunque tratti il
 * paziente. Un alert risolto si disattiva (`is_active = false`), non si
 * cancella — storicità.
 */
#[Fillable(['patient_id', 'category', 'description'])]
class DentalAlert extends Model
{
    use Auditable, UsesTenantConnection, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalAlertFactory::new();
    }

    protected function casts(): array
    {
        return [
            'category' => DentalAlertCategory::class,
            'description' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

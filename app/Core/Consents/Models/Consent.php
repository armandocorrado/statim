<?php

namespace App\Core\Consents\Models;

use App\Core\Consents\Enums\ConsentCollectionMethod;
use App\Core\Consents\Enums\ConsentPurpose;
use App\Core\Consents\Enums\PolicyVersion;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\UsesTenantConnection;
use App\Models\User;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un record per ogni ciclo concessione -> (eventuale) revoca. Non ha né
 * rotta né metodo di cancellazione: la revoca è un update di `revoked_at`,
 * mai una delete — lo storico (fu dato, poi revocato quando) non si tocca.
 * Una nuova concessione dopo una revoca crea una nuova riga.
 */
#[Fillable([
    'patient_id', 'given_by_patient_id', 'purpose', 'collection_method',
    'policy_version', 'granted_at', 'recorded_by',
])]
class Consent extends Model
{
    use UsesTenantConnection, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return ConsentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'collection_method' => ConsentCollectionMethod::class,
            'policy_version' => PolicyVersion::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'given_by_patient_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}

<?php

namespace App\Core\Patients\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Consents\Models\Consent;
use App\Core\Patients\Enums\PatientSource;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'first_name', 'last_name', 'date_of_birth', 'gender', 'birth_place',
    'fiscal_code', 'email', 'mobile_phone', 'landline_phone',
    'address_street', 'address_postal_code', 'address_city', 'address_province',
    'residence_street', 'residence_postal_code', 'residence_city', 'residence_province',
    'vat_number', 'source', 'guardian_patient_id', 'guardian_relationship',
    'notes', 'is_active',
])]
class Patient extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PatientFactory::new();
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'source' => PatientSource::class,
            // Cifrati: dati di contatto/identificativi a bassa esigenza di
            // filtro SQL. address_postal_code/city/province e
            // residence_postal_code/city/province restano in chiaro apposta
            // — servono a interrogazioni tipo "pazienti per città".
            'fiscal_code' => 'encrypted',
            'email' => 'encrypted',
            'mobile_phone' => 'encrypted',
            'landline_phone' => 'encrypted',
            'address_street' => 'encrypted',
            'residence_street' => 'encrypted',
            'vat_number' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Tutore/referente/intestatario fatture, se diverso dal paziente stesso.
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(self::class, 'guardian_patient_id');
    }

    /**
     * Pazienti (tipicamente minori) di cui questo paziente è il tutore/referente.
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'guardian_patient_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Età legale non accertabile (data di nascita assente) = trattato come
     * maggiorenne — limite noto, non un'inferenza di sicurezza.
     */
    public function isMinor(): bool
    {
        return $this->date_of_birth !== null && $this->date_of_birth->age < 18;
    }
}

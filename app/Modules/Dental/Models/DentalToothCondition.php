<?php

namespace App\Modules\Dental\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Dental\Enums\ToothCondition;
use Database\Factories\DentalToothConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, come DentalDiaryEntry: registrare "oggi otturato" non
 * modifica la riga "cariato" di ieri, ne aggiunge una nuova — nessuna
 * rotta di update/delete. Lo stato attuale di un dente è derivato dal
 * record più recente, vedi ToothConditionResolver.
 */
#[Fillable(['patient_id', 'operator_id', 'tooth_number', 'condition_type', 'recorded_date', 'diary_entry_id', 'notes'])]
class DentalToothCondition extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalToothConditionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'recorded_date' => 'date',
            'condition_type' => ToothCondition::class,
            'notes' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function diaryEntry(): BelongsTo
    {
        return $this->belongsTo(DentalDiaryEntry::class, 'diary_entry_id');
    }
}

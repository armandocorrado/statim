<?php

namespace App\Modules\Dental\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;
use Database\Factories\DentalDiaryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: una nota di seduta non si corregge, si annota con una
 * nuova voce — nessuna rotta di update/delete su questo model. `section`
 * è dove si applica il permesso parziale dell'igienista.
 */
#[Fillable(['patient_id', 'operator_id', 'entry_date', 'section', 'content'])]
class DentalDiaryEntry extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalDiaryEntryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'section' => DentalRecordSection::class,
            'content' => 'encrypted',
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
}

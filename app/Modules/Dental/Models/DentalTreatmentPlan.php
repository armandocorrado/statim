<?php

namespace App\Modules\Dental\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\DentalTreatmentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contenitore clinico — nessuno stato proprio, a differenza del
 * preventivo che genera (vedi CLAUDE.md, "Preventivi e piani di cura").
 * Dental dipende liberamente da App\Core\Quotes (il verticale può sempre
 * dipendere dal core): quotes() risolve il verso di relazione che Core
 * stesso non può dichiarare, essendo source_treatment_plan_id un
 * riferimento opaco senza FK dal lato Quote.
 */
#[Fillable(['patient_id', 'title', 'notes'])]
class DentalTreatmentPlan extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalTreatmentPlanFactory::new();
    }

    protected function casts(): array
    {
        return [
            'notes' => 'encrypted',
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

    public function items(): HasMany
    {
        return $this->hasMany(DentalTreatmentPlanItem::class, 'treatment_plan_id')->orderBy('sort_order');
    }

    /**
     * Un piano può generare più preventivi nel tempo (es. una revisione
     * dopo trattativa) — ognuno resta uno snapshot indipendente.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'source_treatment_plan_id');
    }
}

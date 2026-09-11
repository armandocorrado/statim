<?php

namespace App\Modules\Dental\Models;

use App\Core\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DentalTreatmentPlanItemToothFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stesso pattern di DentalDocumentTooth (one-to-many verso un codice FDI,
 * non un'entità "dente"), ma qui mutabile: segue il ciclo di vita della
 * voce di piano a cui appartiene, sostituita insieme ad essa.
 */
#[Fillable(['treatment_plan_item_id', 'tooth_number'])]
class DentalTreatmentPlanItemTooth extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalTreatmentPlanItemToothFactory::new();
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(DentalTreatmentPlanItem::class, 'treatment_plan_item_id');
    }
}

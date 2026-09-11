<?php

namespace App\Modules\Dental\Models;

use App\Core\Quotes\Models\QuoteLine;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DentalTreatmentPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A differenza del resto della cartella clinica, NON è append-only —
 * vedi la migration per il perché. Sostituita in blocco insieme alle
 * altre voci del piano a ogni salvataggio, non un'API di CRUD singola.
 */
#[Fillable(['treatment_plan_id', 'service_catalog_item_id', 'quantity', 'notes', 'session_group', 'sort_order'])]
class DentalTreatmentPlanItem extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return DentalTreatmentPlanItemFactory::new();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'notes' => 'encrypted',
        ];
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(DentalTreatmentPlan::class, 'treatment_plan_id');
    }

    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(DentalTreatmentPlanItemTooth::class, 'treatment_plan_item_id');
    }

    /**
     * Verso Core, permesso liberamente (Dental dipende da Core) — risolve
     * il riferimento opaco che QuoteLine porta con sé senza interpretarlo.
     */
    public function quoteLines(): HasMany
    {
        return $this->hasMany(QuoteLine::class, 'source_treatment_plan_item_id');
    }
}

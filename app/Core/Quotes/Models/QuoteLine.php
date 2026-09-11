<?php

namespace App\Core\Quotes\Models;

use App\Core\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\QuoteLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `description` è testo libero congelato alla generazione — mai un join
 * live, coerente con BillingDocumentLine. `source_treatment_plan_item_id`
 * è un riferimento opaco (nessuna FK, nessuna relazione Eloquent qui):
 * Core non deve mai sapere cos'è un DentalTreatmentPlanItem — chi vuole
 * risalire dalla voce clinica al preventivo lo fa dal lato Dental, vedi
 * DentalTreatmentPlanItem::quoteLines().
 */
#[Fillable([
    'service_catalog_item_id', 'source_treatment_plan_item_id',
    'description', 'quantity', 'unit_price', 'discount_percent',
    'vat_rate', 'vat_exemption_reason', 'sort_order',
])]
class QuoteLine extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return QuoteLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function serviceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalogItem::class);
    }

    public function isVatExempt(): bool
    {
        return $this->vat_rate === null || (float) $this->vat_rate === 0.0;
    }

    /**
     * quantità × prezzo unitario, scontato — la base su cui
     * BillingDocumentTotalsCalculator (riusato tal quale) applica poi
     * l'IVA. Lo sconto si applica qui, non nel calculator: quella classe
     * resta quella già testata per Billing, senza doverla insegnare a
     * conoscere gli sconti.
     */
    public function discountedUnitPrice(): float
    {
        $discount = $this->discount_percent !== null ? (float) $this->discount_percent : 0.0;

        return round((float) $this->unit_price * (1 - $discount / 100), 2);
    }
}

<?php

namespace App\Core\Quotes\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Tenancy\Concerns\UsesTenantConnection;
use App\Models\User;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Preventivo — CORE trasversale. Vedi CLAUDE.md, sezione "Preventivi e
 * piani di cura", per il confine con il verticale Dental che lo genera.
 * Nessun trait Auditable "silenzioso" con azioni nominate: il diff
 * generico basta, stesso principio di BillingDocument.
 */
#[Fillable(['patient_id', 'source_treatment_plan_id'])]
class Quote extends Model
{
    use Auditable, UsesTenantConnection, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return QuoteFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'issued_at' => 'date',
            'responded_at' => 'date',
            'total_taxable' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort_order');
    }

    /**
     * Un preventivo può generare più documenti fiscali nel tempo (es.
     * fatturazione a fasi/acconti) — 1:N, non 1:1, per scelta esplicita.
     */
    public function billingDocuments(): HasMany
    {
        return $this->hasMany(BillingDocument::class, 'source_quote_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === QuoteStatus::Draft;
    }

    public function isEligibleForBillingDocument(): bool
    {
        return in_array($this->status, QuoteStatus::acceptedStatuses(), true);
    }
}

<?php

namespace App\Core\Billing\Models;

use App\Core\Audit\Concerns\Auditable;
use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Enums\FiscalChannel;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\BillingDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento fiscale (fattura). Una volta `Issued`, `document_number` +
 * `document_year` sono permanenti e i totali sono congelati — nessuna
 * rotta di update/delete per un documento emesso, solo per le bozze.
 * Correggere un documento emesso richiederà una nota di credito (fuori
 * scope qui). `patient_id` è sempre il paziente a cui si riferisce la
 * prestazione (serve al Sistema TS anche quando si fattura a un
 * intestatario diverso); i campi `recipient_*` sono una copia congelata
 * dei dati fiscali del destinatario al momento della creazione, non un
 * join live su Patient.
 *
 * Nessun trait Auditable "silenzioso" con azioni nominate come per
 * Consent: qui l'evento di emissione cambia molti campi insieme (stato,
 * numero, canale, totali) e il diff generico di Auditable è già di per sé
 * leggibile — non serve un'azione nominata separata.
 */
#[Fillable([
    'patient_id', 'recipient_patient_id',
    'recipient_name', 'recipient_fiscal_code', 'recipient_vat_number',
    'recipient_address_street', 'recipient_address_postal_code',
    'recipient_address_city', 'recipient_address_province',
])]
class BillingDocument extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return BillingDocumentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => BillingDocumentStatus::class,
            'fiscal_channel' => FiscalChannel::class,
            'issued_at' => 'date',
            'recipient_name' => 'encrypted',
            'recipient_fiscal_code' => 'encrypted',
            'recipient_vat_number' => 'encrypted',
            'recipient_address_street' => 'encrypted',
            'total_taxable' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'recipient_patient_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingDocumentLine::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === BillingDocumentStatus::Draft;
    }

    /**
     * Numero documento formattato all'italiana, es. "12/2026".
     */
    public function displayNumber(): ?string
    {
        if ($this->document_number === null) {
            return null;
        }

        return "{$this->document_number}/{$this->document_year}";
    }

    /**
     * @return list<string>
     */
    public static function auditExcludedAttributes(): array
    {
        return ['recipient_fiscal_code', 'recipient_vat_number'];
    }
}

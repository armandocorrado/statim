<?php

namespace App\Core\Billing\Models;

use App\Core\Tenancy\Concerns\UsesTenantConnection;
use Database\Factories\BillingDocumentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'description', 'quantity', 'unit_price',
    'vat_rate', 'vat_exemption_reason', 'sort_order',
])]
class BillingDocumentLine extends Model
{
    use UsesTenantConnection, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return BillingDocumentLineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }

    public function isVatExempt(): bool
    {
        return $this->vat_rate === null || (float) $this->vat_rate === 0.0;
    }
}

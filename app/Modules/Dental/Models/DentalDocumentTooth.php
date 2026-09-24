<?php

namespace App\Modules\Dental\Models;

use App\Core\Tenancy\Concerns\UsesTenantConnection;
use Database\Factories\DentalDocumentToothFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga append-only: creata solo al momento dell'upload del documento
 * collegato, mai dopo — nessuna rotta di update/delete. Non ha una propria
 * Auditable: è creata nello stesso istante/stesso attore del documento
 * (già tracciato dal trait Auditable su DentalDocument), una seconda voce
 * di audit sarebbe rumore duplicato, non nuova informazione.
 */
#[Fillable(['document_id', 'tooth_number'])]
class DentalDocumentTooth extends Model
{
    use UsesTenantConnection, HasFactory, HasUlids;

    const UPDATED_AT = null;

    protected static function newFactory(): Factory
    {
        return DentalDocumentToothFactory::new();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DentalDocument::class, 'document_id');
    }
}

<?php

namespace App\Core\Billing\Models;

use App\Core\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Una riga per tenant+anno, mai esposta fuori da
 * App\Core\Billing\Support\BillingDocumentNumberer — vedi lì per il perché.
 */
#[Fillable(['tenant_id', 'year', 'next_number'])]
class BillingDocumentCounter extends Model
{
    use BelongsToTenant, HasUlids;
}

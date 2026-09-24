<?php

namespace App\Core\Billing\Models;

use App\Core\Tenancy\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Una riga per anno, mai esposta fuori da
 * App\Core\Billing\Support\BillingDocumentNumberer — vedi lì per il perché.
 */
#[Fillable(['year', 'next_number'])]
class BillingDocumentCounter extends Model
{
    use UsesTenantConnection, HasUlids;
}

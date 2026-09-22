<?php

namespace App\Core\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mappa email->studio nel DB centrale: usata dal futuro login (Tappa 2) per
 * sapere a quale studio appartiene un'email prima ancora di verificarne la
 * password, e per la schermata di scelta studio nel caso di email presenti
 * in piu' studi. Nessun hash password qui: la verifica credenziali resta
 * sempre nel DB dello studio scelto.
 */
#[Fillable(['email', 'tenant_id', 'remote_user_id', 'is_active'])]
class TenantUser extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

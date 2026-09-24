<?php

namespace App\Core\Users\Models;

use App\Core\Tenancy\Concerns\UsesTenantConnection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Sottoclasse per una sola ragione: forzare la connessione 'tenant' sempre,
 * indipendentemente dal bridge di TenantConnectionResolver (che flippa
 * database.default solo dentro forTenant() — mai attivato, ad esempio, da
 * actingAs() nei test, che salta la sessione e quindi RestoreTenantConnection).
 * I model spatie di base non hanno il trait: senza questa sottoclasse
 * risolverebbero silenziosamente sulla connessione di default sbagliata.
 * Vedi config/permission.php ('models.role').
 */
class Role extends SpatieRole
{
    use UsesTenantConnection;
}

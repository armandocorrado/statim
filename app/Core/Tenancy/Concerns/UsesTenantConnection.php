<?php

namespace App\Core\Tenancy\Concerns;

/**
 * Successore di BelongsToTenant (Tappa 3): con database separati per studio,
 * l'isolamento e' fisico (la connessione), non piu' logico (colonna
 * tenant_id + global scope). L'unica responsabilita' rimasta e' dichiarare
 * la connessione - stesso concetto usato direttamente da Tenant/TenantUser
 * verso 'central', qui verso 'tenant' (risolta a runtime da
 * TenantConnectionResolver, mai impostata qui in modo dinamico).
 *
 * Un metodo, non una proprieta' $connection: PHP non accetta che un trait
 * dichiari una proprieta' gia' definita (con default diverso) nella classe
 * base Eloquent Model — "definitions differs and is considered
 * incompatible" — mentre sovrascrivere il metodo getConnectionName() e'
 * la normale risoluzione dei metodi, senza questa restrizione.
 */
trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        return 'tenant';
    }
}

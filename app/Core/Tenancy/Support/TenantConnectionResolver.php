<?php

namespace App\Core\Tenancy\Support;

use App\Core\Tenancy\Exceptions\InactiveTenantException;
use App\Core\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Commuta a runtime la connessione 'tenant' sul database fisico dello studio
 * corrente. Nessuna magia di container: un servizio esplicito, invocabile sia
 * nel ciclo HTTP (login, Tappa 2) sia da comandi/job fuori da esso, dove non
 * esiste un tenant "magico" (stesso principio gia' documentato per il vecchio
 * meccanismo a DB condiviso).
 *
 * Registrato come singleton: la stessa istanza vive per tutta la request/il
 * comando, cosi' current() riflette sempre l'ultimo tenant risolto.
 */
class TenantConnectionResolver
{
    private ?Tenant $current = null;

    public function forTenant(Tenant $tenant): void
    {
        if (! $tenant->is_active) {
            throw new InactiveTenantException("Lo studio [{$tenant->slug}] non e' attivo.");
        }

        Config::set('database.connections.tenant.database', $tenant->database_name);
        DB::purge('tenant');

        // Fail-fast: scopriamo subito un DB irraggiungibile/inesistente,
        // non alla prima query reale eseguita altrove nel codice.
        DB::connection('tenant')->getPdo();

        $this->current = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    /**
     * Torna allo stato "nessuno studio selezionato". Da chiamare sempre a
     * fine job in un worker di coda riusato tra job di studi diversi, per non
     * far trapelare la connessione di un job nel successivo.
     */
    public function release(): void
    {
        Config::set('database.connections.tenant.database', null);
        DB::purge('tenant');

        $this->current = null;
    }

    /**
     * Esegue $callback nel contesto di $tenant, poi ripristina lo stato
     * precedente (un altro tenant risolto, o nessuno).
     */
    public function usingTenant(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->current;

        $this->forTenant($tenant);

        try {
            return $callback();
        } finally {
            $previous ? $this->forTenant($previous) : $this->release();
        }
    }
}

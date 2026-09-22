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

    /**
     * La connessione di default dell'app PRIMA di qualunque risoluzione
     * tenant in questo ciclo di vita (request/comando) — a cosa tornare in
     * release(). Catturata alla creazione del singleton, non hardcoded,
     * cosi' resta corretta sia in produzione/sviluppo (mysql) sia nei test
     * (sqlite).
     */
    private readonly string $originalDefaultConnection;

    public function __construct()
    {
        $this->originalDefaultConnection = DB::getDefaultConnection();
    }

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

        // Bridge temporaneo (Tappa 2): i 22 modelli di dominio non
        // dichiarano ancora esplicitamente connection='tenant' (arriva con
        // la Tappa 3, stesso pattern gia' usato da Tenant/TenantUser verso
        // 'central'). Fino ad allora, far diventare 'tenant' la connessione
        // di default dell'app e' l'unico modo perche' quei modelli seguano
        // lo studio risolto senza toccarli uno per uno adesso.
        DB::setDefaultConnection('tenant');

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
        DB::setDefaultConnection($this->originalDefaultConnection);

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

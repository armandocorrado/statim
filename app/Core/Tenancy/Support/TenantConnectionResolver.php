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

    /**
     * Il valore di config('database.connections.tenant.database') PRIMA di
     * qualunque forTenant() in questo ciclo di vita — a cosa tornare in
     * release(). Nei test è quasi sempre ':memory:' (fallback sqlite di
     * config/database.php): un null hardcoded qui romperebbe la connessione
     * condivisa dei test che, dopo un release(), continuano a usare 'tenant'
     * senza aver mai risolto un altro Tenant reale (es. userWithRole() dopo
     * aver provisionato uno studio isolato in un altro test/helper).
     */
    private readonly mixed $originalTenantDatabase;

    public function __construct()
    {
        $this->originalDefaultConnection = DB::getDefaultConnection();
        $this->originalTenantDatabase = Config::get('database.connections.tenant.database');
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

        // Bridge NON piu' temporaneo (rivalutato in Tappa 3, quando ogni
        // modello di dominio ha gia' ottenuto UsesTenantConnection): resta
        // necessario perche' ~22 FormRequest in tutta l'app usano
        // Rule::exists('tabella', ...)/Rule::unique('tabella', ...) con un
        // nome di tabella nudo, non un model Eloquent — quelle query girano
        // via DB::table() sulla connessione di DEFAULT dell'app, non su
        // 'tenant', e non seguono UsesTenantConnection. Senza questa riga
        // ogni validazione con Rule::exists/Rule::unique in produzione
        // risolverebbe sul database sbagliato. Rimuoverlo richiederebbe
        // riscrivere tutti quei call site con la sintassi 'tenant.tabella' o
        // ->using(), non solo eliminare questa riga.
        DB::setDefaultConnection('tenant');

        $this->current = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    /**
     * Attacca l'identità di $tenant a QUALUNQUE cosa la connessione 'tenant'
     * stia già puntando, senza toccare config/PDO — a differenza di
     * forTenant(), non fa Config::set()/DB::purge(). Serve solo ai test
     * Feature che usano actingAs() (che salta la sessione, quindi
     * RestoreTenantConnection non gira mai e current() resterebbe null):
     * la connessione condivisa ':memory:' di quei test è già corretta,
     * manca solo l'oggetto Tenant per il codice applicativo che lo legge
     * (es. UserController::storeInvitation() per comporre l'email di
     * invito). Mai usare fuori dai test: non verifica is_active né
     * risolve realmente alcun database.
     */
    public function setCurrentForTesting(Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    /**
     * Torna allo stato "nessuno studio selezionato". Da chiamare sempre a
     * fine job in un worker di coda riusato tra job di studi diversi, per non
     * far trapelare la connessione di un job nel successivo.
     */
    public function release(): void
    {
        Config::set('database.connections.tenant.database', $this->originalTenantDatabase);
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

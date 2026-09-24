<?php

namespace Tests;

/**
 * Per i test che chiamano esplicitamente TenantConnectionResolver
 * (forTenant/release/usingTenant) e quindi DB::purge('tenant') internamente:
 * un purge() a meta' test rimpiazza il PDO della connessione con uno nuovo
 * MAI iniziato in una transazione, e RefreshDatabase (che verifica "il PDO
 * e' ancora in transazione?" a fine test per decidere se re-migrare tutto)
 * lo interpreta come segnale di corruzione, forzando un re-migrate che poi
 * fallisce su 'central' (schema gia' esistente, mai droppato perche'
 * migrate:fresh droppa solo la connessione di default).
 *
 * Questi test gestiscono gia' da soli i propri database temporanei (file
 * sqlite reali, non ':memory:' condiviso) — non hanno bisogno che 'tenant'
 * partecipi al rollback automatico per-test.
 */
abstract class TenancySwitchingTestCase extends TestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = ['sqlite', 'central'];
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 'central' (registro tenant/tenant_users) e 'tenant' (schema di
     * dominio, Tappa 3: ogni model di dominio vive lì) aggiunte alla
     * connessione di default: senza, le righe create nei test non
     * verrebbero racchiuse nello stesso rollback per-test del resto, con
     * rischio di dati accumulati tra un test e l'altro o schema mancante
     * dal secondo test in poi.
     *
     * Deve essere una PROPRIETA', non un override del metodo
     * connectionsToTransact(): RefreshDatabase::connectionsToTransact()
     * legge `property_exists($this, 'connectionsToTransact')`. Un metodo
     * definito qui verrebbe invece oscurato dal metodo dello stesso nome nel
     * trait quando Pest applica ->use(RefreshDatabase::class) alla
     * sottoclasse generata (un trait usato direttamente in una classe ha
     * precedenza su un metodo solo ereditato da una classe genitore).
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['sqlite', 'central', 'tenant'];
}

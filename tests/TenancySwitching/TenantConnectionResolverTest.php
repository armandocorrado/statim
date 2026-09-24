<?php

use App\Core\Tenancy\Exceptions\InactiveTenantException;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use Illuminate\Support\Facades\Schema;

function touchTempSqliteFile(): string
{
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    return $path;
}

it('imposta la connessione tenant e current() la riflette', function () {
    $tenant = Tenant::factory()->create(['database_name' => ':memory:']);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    expect($resolver->current()->is($tenant))->toBeTrue()
        ->and(config('database.connections.tenant.database'))->toBe(':memory:');
});

it('lancia InactiveTenantException per un tenant non attivo, senza mutare lo stato', function () {
    $tenant = Tenant::factory()->create(['database_name' => ':memory:', 'is_active' => false]);

    $resolver = app(TenantConnectionResolver::class);

    expect(fn () => $resolver->forTenant($tenant))->toThrow(InactiveTenantException::class);
    expect($resolver->current())->toBeNull();
});

it('release() torna allo stato nessun tenant selezionato', function () {
    $originalTenantDatabase = config('database.connections.tenant.database');
    $tenant = Tenant::factory()->create(['database_name' => ':memory:']);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);
    $resolver->release();

    // Torna al valore ORIGINALE (di solito ':memory:' nei test), non a null
    // a prescindere: un null hardcoded romperebbe la connessione condivisa
    // dei test che, dopo un release(), continuano a usare 'tenant' senza
    // aver risolto un altro Tenant reale.
    expect($resolver->current())->toBeNull()
        ->and(config('database.connections.tenant.database'))->toBe($originalTenantDatabase);
});

it('usingTenant() ripristina il tenant precedente dopo la callback', function () {
    // Laravel verifica che il file sqlite esista PRIMA di connettersi (non lo
    // crea al volo come farebbe PDO da solo): serve un file reale distinto
    // per ciascuno dei due tenant, non ':memory:' per entrambi (violerebbe
    // il vincolo unico su database_name) ne' un percorso ':memory:'-simile
    // inventato (non e' un valore magico riconosciuto, verrebbe letto come
    // path di file letterale inesistente).
    $pathA = touchTempSqliteFile();
    $pathB = touchTempSqliteFile();

    $tenantA = Tenant::factory()->create(['database_name' => $pathA]);
    $tenantB = Tenant::factory()->create(['database_name' => $pathB]);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenantA);

    $seenDuringCallback = $resolver->usingTenant($tenantB, fn () => $resolver->current()->id);

    expect($seenDuringCallback)->toBe($tenantB->id)
        ->and($resolver->current()->id)->toBe($tenantA->id);

    // Su Windows un file sqlite resta bloccato finche' la connessione e'
    // aperta: va rilasciata prima di poterlo cancellare.
    $resolver->release();
    unlink($pathA);
    unlink($pathB);
});

it('usingTenant() rilascia la connessione se non c\'era un tenant precedente', function () {
    $tenant = Tenant::factory()->create(['database_name' => ':memory:']);

    $resolver = app(TenantConnectionResolver::class);

    $resolver->usingTenant($tenant, fn () => null);

    expect($resolver->current())->toBeNull();
});

it('forTenant() fa diventare tenant la connessione di default (bridge Tappa 2)', function () {
    $originalDefault = config('database.default');
    $tenant = Tenant::factory()->create(['database_name' => ':memory:']);

    app(TenantConnectionResolver::class)->forTenant($tenant);

    expect(config('database.default'))->toBe('tenant')
        ->and($originalDefault)->not->toBe('tenant');
});

it('release() ripristina la connessione di default originale dell\'app', function () {
    $originalDefault = config('database.default');
    $tenant = Tenant::factory()->create(['database_name' => ':memory:']);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);
    $resolver->release();

    expect(config('database.default'))->toBe($originalDefault);
});

it('Tenant vive sulla connessione central, non su quella di default', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->getConnectionName())->toBe('central')
        ->and(Schema::connection('central')->hasTable('tenants'))->toBeTrue()
        ->and(Schema::connection('central')->hasTable('tenant_users'))->toBeTrue()
        ->and(Schema::connection(config('database.default'))->hasTable('tenants'))->toBeFalse();
});

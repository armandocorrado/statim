<?php

use App\Core\Tenancy\Support\TenantConnectionResolver;

/**
 * Con database separati, un utente di un altro studio non esiste affatto
 * nel DB dello studio corrente: il model binding di rotta su {user} non
 * trova nulla e la richiesta è 404, non 403 come quando l'isolamento era
 * garantito da un confronto tenant_id + Policy. Stesso principio già
 * applicato in tests/TenancySwitching/QuoteCrossDatabaseIsolationTest.php.
 */
test('the permission cannot be granted to a user from another studio (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('odontoiatra');
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('admin');

    $this->actingAs($studioA['user'])->patch("/users/{$studioB['user']->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

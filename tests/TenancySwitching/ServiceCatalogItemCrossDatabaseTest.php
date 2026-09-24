<?php

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Support\TenantConnectionResolver;

test('a service catalog item cannot be updated across tenants (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('segreteria');
    $itemB = ServiceCatalogItem::factory()->create();
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('segreteria');

    $this->actingAs($studioA['user'])->put("/service-catalog/{$itemB->id}", [
        'name' => 'hijack', 'base_price' => 1, 'is_active' => true,
    ])->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

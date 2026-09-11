<?php

use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Models\Tenant;

test('segreteria can create and update a service catalog item', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');

    $response = $this->actingAs($segreteria)->post('/service-catalog', [
        'name' => 'Sbiancamento',
        'category' => 'general',
        'base_price' => 150,
        'default_vat_rate' => null,
        'default_vat_exemption_reason' => 'art. 10 n. 18 DPR 633/72',
        'default_duration_minutes' => 45,
    ]);

    $response->assertSessionHasNoErrors();
    $item = ServiceCatalogItem::where('name', 'Sbiancamento')->firstOrFail();

    $this->actingAs($segreteria)->put("/service-catalog/{$item->id}", [
        'name' => 'Sbiancamento professionale',
        'category' => 'general',
        'base_price' => 180,
        'default_vat_rate' => null,
        'default_vat_exemption_reason' => null,
        'default_duration_minutes' => 45,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($item->fresh()->name)->toBe('Sbiancamento professionale')
        ->and((float) $item->fresh()->base_price)->toBe(180.0);
});

test('odontoiatra can view the catalog but not create or edit items', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $item = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->get('/service-catalog')->assertOk();

    $this->actingAs($odontoiatra)->post('/service-catalog', [
        'name' => 'Otturazione extra', 'base_price' => 90,
    ])->assertForbidden();

    $this->actingAs($odontoiatra)->put("/service-catalog/{$item->id}", [
        'name' => 'x', 'base_price' => 1, 'is_active' => true,
    ])->assertForbidden();
});

test('aso cannot view the service catalog', function () {
    $tenant = Tenant::factory()->create();
    $aso = userForTenant($tenant, 'aso');

    $this->actingAs($aso)->get('/service-catalog')->assertForbidden();
});

test('a service catalog item cannot be updated across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $segreteriaA = userForTenant($tenantA, 'segreteria');
    $itemB = ServiceCatalogItem::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($segreteriaA)->put("/service-catalog/{$itemB->id}", [
        'name' => 'hijack', 'base_price' => 1, 'is_active' => true,
    ])->assertForbidden();
});

<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;
use App\Core\Tenancy\Models\Tenant;

test('an odontoiatra without the permission cannot edit quote prices', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Otturazione', 'quantity' => 1, 'unit_price' => 90]],
    ])->assertForbidden();
});

test('admin can grant the price-edit permission to a specific odontoiatra, who can then edit prices', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $otherOdontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($odontoiatra)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Otturazione', 'quantity' => 1, 'unit_price' => 90]],
    ])->assertSessionHasNoErrors();

    expect($quote->lines()->count())->toBe(1);

    // Il permesso è per-utente, non per-ruolo: un altro odontoiatra dello
    // stesso tenant, non abilitato individualmente, resta bloccato.
    $this->actingAs($otherOdontoiatra)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Radiografia', 'quantity' => 1, 'unit_price' => 30]],
    ])->assertForbidden();
});

test('an odontoiatra enabled to edit prices still cannot issue, delete, or transition the quote', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $draftQuote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    $issuedQuote = Quote::factory()->issued()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", ['enabled' => true]);

    $this->actingAs($odontoiatra)->patch("/quotes/{$draftQuote->id}/issue")->assertForbidden();
    $this->actingAs($odontoiatra)->delete("/quotes/{$draftQuote->id}")->assertForbidden();
    $this->actingAs($odontoiatra)->patch("/quotes/{$issuedQuote->id}/status", ['status' => 'accepted'])->assertForbidden();

    expect($draftQuote->fresh()->status->value)->toBe('draft');
});

test('igienista can also be individually enabled to edit prices', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/users/{$igienista->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($igienista)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Igiene', 'quantity' => 1, 'unit_price' => 70]],
    ])->assertSessionHasNoErrors();
});

test('only admin can grant or revoke the price-edit permission', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');

    $this->actingAs($segreteria)->patch("/users/{$odontoiatra->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertForbidden();

    $this->actingAs($odontoiatra)->patch("/users/{$odontoiatra->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertForbidden();
});

test('the permission cannot be granted to a non-dentist role', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $segreteria = userForTenant($tenant, 'segreteria');
    $aso = userForTenant($tenant, 'aso');

    $this->actingAs($admin)->patch("/users/{$segreteria->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertInvalid(['enabled']);

    $this->actingAs($admin)->patch("/users/{$aso->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertInvalid(['enabled']);
});

test('granting and revoking the price-edit permission is recorded in the audit log', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", ['enabled' => true]);

    $grantLog = AuditLog::where('action', 'quote_price_permission_granted')
        ->where('auditable_id', $odontoiatra->id)
        ->firstOrFail();
    expect($grantLog->new_values)->toBe(['treatment_plans.prices.edit' => true])
        ->and($grantLog->old_values)->toBe(['treatment_plans.prices.edit' => false])
        ->and($grantLog->user_id)->toBe($admin->id);

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", ['enabled' => false]);

    $revokeLog = AuditLog::where('action', 'quote_price_permission_revoked')
        ->where('auditable_id', $odontoiatra->id)
        ->firstOrFail();
    expect($revokeLog->new_values)->toBe(['treatment_plans.prices.edit' => false])
        ->and($revokeLog->old_values)->toBe(['treatment_plans.prices.edit' => true]);
});

test('the permission cannot be granted across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');
    $odontoiatraB = userForTenant($tenantB, 'odontoiatra');

    // Il model binding implicito su {user} precede il middleware `tenant`
    // (SubstituteBindings è nel gruppo web globale) — la vera garanzia è
    // la Policy (403), non il binding stesso, stesso principio già
    // documentato per Patient in CLAUDE.md.
    $this->actingAs($adminA)->patch("/users/{$odontoiatraB->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertForbidden();
});

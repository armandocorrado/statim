<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;

test('admin can record a consent for a patient', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)->post("/patients/{$patient->id}/consents", [
        'purpose' => 'marketing',
        'collection_method' => 'cartaceo',
        'policy_version' => 'v1',
        'granted_at' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $consent = Consent::where('patient_id', $patient->id)->firstOrFail();
    expect($consent->purpose)->toBe(\App\Core\Consents\Enums\ConsentPurpose::Marketing)
        ->and($consent->tenant_id)->toBe($tenant->id)
        ->and($consent->recorded_by)->toBe($admin->id)
        ->and($consent->isActive())->toBeTrue();
});

test('odontoiatra cannot record a consent', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/consents", [
        'purpose' => 'marketing',
        'collection_method' => 'cartaceo',
        'policy_version' => 'v1',
        'granted_at' => now()->toDateString(),
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('consents', ['patient_id' => $patient->id]);
});

test('a duplicate active consent for the same purpose is rejected', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    Consent::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'purpose' => 'cura',
    ]);

    $response = $this->actingAs($admin)->post("/patients/{$patient->id}/consents", [
        'purpose' => 'cura',
        'collection_method' => 'cartaceo',
        'policy_version' => 'v1',
        'granted_at' => now()->toDateString(),
    ]);

    $response->assertInvalid(['purpose']);
});

test('admin can revoke an active consent and the row is preserved as history', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $consent = Consent::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'purpose' => 'cura',
    ]);

    $response = $this->actingAs($admin)->patch("/patients/{$patient->id}/consents/{$consent->id}/revoke");

    $response->assertRedirect();
    $consent->refresh();
    expect($consent->isActive())->toBeFalse()
        ->and($consent->revoked_at)->not->toBeNull()
        ->and($consent->purpose)->toBe(\App\Core\Consents\Enums\ConsentPurpose::Cura);

    $this->assertDatabaseHas('consents', ['id' => $consent->id]);
});

test('a consent cannot be revoked across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
    $consentB = Consent::factory()->create([
        'tenant_id' => $tenantB->id,
        'patient_id' => $patientB->id,
    ]);

    $response = $this->actingAs($adminA)->patch("/patients/{$patientB->id}/consents/{$consentB->id}/revoke");

    $response->assertForbidden();
    expect($consentB->fresh()->isActive())->toBeTrue();
});

test('a minor patient consent is attributed to their registered guardian', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $guardian = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $minor = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'date_of_birth' => now()->subYears(10),
        'guardian_patient_id' => $guardian->id,
    ]);

    $this->actingAs($admin)->post("/patients/{$minor->id}/consents", [
        'purpose' => 'cura',
        'collection_method' => 'cartaceo',
        'policy_version' => 'v1',
        'granted_at' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $consent = Consent::where('patient_id', $minor->id)->firstOrFail();
    expect($consent->given_by_patient_id)->toBe($guardian->id);
});

test('an adult patient with a guardian_patient_id set for billing consents for themselves', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $billingPayer = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $adult = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'date_of_birth' => now()->subYears(40),
        'guardian_patient_id' => $billingPayer->id,
        'guardian_relationship' => 'azienda',
    ]);

    $this->actingAs($admin)->post("/patients/{$adult->id}/consents", [
        'purpose' => 'cura',
        'collection_method' => 'cartaceo',
        'policy_version' => 'v1',
        'granted_at' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $consent = Consent::where('patient_id', $adult->id)->firstOrFail();
    expect($consent->given_by_patient_id)->toBeNull();
});

test('granting and revoking a consent are recorded in the audit log', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->post("/patients/{$patient->id}/consents", [
        'purpose' => 'marketing',
        'collection_method' => 'cartaceo',
        'policy_version' => 'v1',
        'granted_at' => now()->toDateString(),
    ]);

    $consent = Consent::where('patient_id', $patient->id)->firstOrFail();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'consent_granted',
        'auditable_id' => $consent->id,
    ]);

    $this->actingAs($admin)->patch("/patients/{$patient->id}/consents/{$consent->id}/revoke");

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'consent_revoked',
        'auditable_id' => $consent->id,
    ]);
});

<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;

test('admin can record a consent for a patient', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();

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
        ->and($consent->recorded_by)->toBe($admin->id)
        ->and($consent->isActive())->toBeTrue();
});

test('odontoiatra cannot record a consent', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

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
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();
    Consent::factory()->create([
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
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();
    $consent = Consent::factory()->create([
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

test('a minor patient consent is attributed to their registered guardian', function () {
    $admin = userWithRole('admin');
    $guardian = Patient::factory()->create();
    $minor = Patient::factory()->create([
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
    $admin = userWithRole('admin');
    $billingPayer = Patient::factory()->create();
    $adult = Patient::factory()->create([
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
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();

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

<?php

use App\Core\Patients\Models\Patient;
use App\Modules\Dental\Models\DentalAlert;

test('odontoiatra can add an allergy alert', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/alerts", [
        'category' => 'allergy',
        'description' => 'Allergia al lattice',
    ]);

    $response->assertSessionHasNoErrors();
    $alert = DentalAlert::where('patient_id', $patient->id)->firstOrFail();
    expect($alert->is_active)->toBeTrue()
        ->and($alert->description)->toBe('Allergia al lattice');
});

test('igienista can also add and see alerts — safety data is not sectioned', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/alerts", [
        'category' => 'risk',
        'description' => 'Paziente cardiopatico',
    ])->assertSessionHasNoErrors();

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental")
        ->assertInertia(fn ($page) => $page->has('alerts', 1));
});

test('segreteria cannot add an alert', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();

    $this->actingAs($segreteria)->post("/patients/{$patient->id}/dental/alerts", [
        'category' => 'allergy',
        'description' => 'Test',
    ])->assertForbidden();
});

test('resolving an alert deactivates it without deleting it', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();
    $alert = DentalAlert::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/patients/{$patient->id}/dental/alerts/{$alert->id}/resolve")
        ->assertSessionHasNoErrors();

    expect($alert->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('dental_alerts', ['id' => $alert->id]);
});

test('a resolved alert no longer appears on the clinical record', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    DentalAlert::factory()->create(['patient_id' => $patient->id, 'is_active' => false]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental")
        ->assertInertia(fn ($page) => $page->has('alerts', 0));
});

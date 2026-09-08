<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;

test('odontoiatra can create an appointment for themselves', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Appointment::where('patient_id', $patient->id)->first()->operator_id)->toBe($odontoiatra->id);
});

test('odontoiatra cannot create an appointment for another operator', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $igienista->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertForbidden();
});

test('segreteria can create an appointment for any operator', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($segreteria)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertSessionHasNoErrors();
});

test('aso cannot create an appointment', function () {
    $tenant = Tenant::factory()->create();
    $aso = userForTenant($tenant, 'aso');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($aso)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertForbidden();
});

test('aso can view the agenda read-only', function () {
    $tenant = Tenant::factory()->create();
    $aso = userForTenant($tenant, 'aso');

    $this->actingAs($aso)->get('/agenda')->assertOk();
});

test('odontoiatra cannot update another operators appointment', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $igienista->id,
        'patient_id' => $patient->id,
    ]);

    $response = $this->actingAs($odontoiatra)->patch("/agenda/appointments/{$appointment->id}", [
        'patient_id' => $patient->id,
        'operator_id' => $igienista->id,
        'start_at' => $appointment->start_at->toDateTimeString(),
        'end_at' => $appointment->end_at->toDateTimeString(),
        'status' => 'confirmed',
    ]);

    $response->assertForbidden();
});

test('odontoiatra cannot reassign their own appointment to another operator', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patient->id,
    ]);

    $response = $this->actingAs($odontoiatra)->patch("/agenda/appointments/{$appointment->id}", [
        'patient_id' => $patient->id,
        'operator_id' => $igienista->id,
        'start_at' => $appointment->start_at->toDateTimeString(),
        'end_at' => $appointment->end_at->toDateTimeString(),
        'status' => 'scheduled',
    ]);

    $response->assertInvalid(['operator_id']);
});

test('an appointment cannot be updated across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');
    $operatorB = userForTenant($tenantB, 'odontoiatra');
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenantB->id,
        'operator_id' => $operatorB->id,
        'patient_id' => $patientB->id,
    ]);

    $response = $this->actingAs($adminA)->patch("/agenda/appointments/{$appointment->id}", [
        'patient_id' => $patientB->id,
        'operator_id' => $operatorB->id,
        'start_at' => $appointment->start_at->toDateTimeString(),
        'end_at' => $appointment->end_at->toDateTimeString(),
        'status' => 'confirmed',
    ]);

    $response->assertForbidden();
});

<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;

test('two appointments for the same operator cannot overlap', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patientA = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $patientB = Patient::factory()->create(['tenant_id' => $tenant->id]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:15:00',
        'end_at' => '2027-01-10 10:45:00',
    ]);

    $response->assertInvalid(['overlap']);
    expect(Appointment::where('patient_id', $patientB->id)->count())->toBe(0);
});

test('back-to-back appointments for the same operator do not overlap', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patientA = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $patientB = Patient::factory()->create(['tenant_id' => $tenant->id]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:30:00',
        'end_at' => '2027-01-10 11:00:00',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Appointment::where('patient_id', $patientB->id)->exists())->toBeTrue();
});

test('two appointments for different operators at the same time do not overlap', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $igienista = userForTenant($tenant, 'igienista');
    $patientA = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $patientB = Patient::factory()->create(['tenant_id' => $tenant->id]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $igienista->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertSessionHasNoErrors();
});

test('a cancelled appointment does not block a new one in the same slot', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patientA = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $patientB = Patient::factory()->create(['tenant_id' => $tenant->id]);

    Appointment::factory()->cancelled()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertSessionHasNoErrors();
});

test('updating an appointment into an overlap with another is rejected', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patientA = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $patientB = Patient::factory()->create(['tenant_id' => $tenant->id]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);
    $appointmentB = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patientB->id,
        'start_at' => '2027-01-10 11:00:00',
        'end_at' => '2027-01-10 11:30:00',
    ]);

    $response = $this->actingAs($admin)->patch("/agenda/appointments/{$appointmentB->id}", [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:15:00',
        'end_at' => '2027-01-10 10:45:00',
        'status' => 'scheduled',
    ]);

    $response->assertInvalid(['overlap']);
});

test('updating an appointment without changing its own slot is not blocked by itself', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patient->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->patch("/agenda/appointments/{$appointment->id}", [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
        'status' => 'confirmed',
        'notes' => 'Confermato telefonicamente',
    ]);

    $response->assertSessionHasNoErrors();
    expect($appointment->fresh()->status)->toBe(\App\Core\Agenda\Enums\AppointmentStatus::Confirmed);
});

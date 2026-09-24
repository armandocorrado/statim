<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;

test('odontoiatra can create an appointment for themselves', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

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
    $odontoiatra = userWithRole('odontoiatra');
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($odontoiatra)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $igienista->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertForbidden();
});

test('segreteria can create an appointment for any operator', function () {
    $segreteria = userWithRole('segreteria');
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($segreteria)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertSessionHasNoErrors();
});

test('aso cannot create an appointment', function () {
    $aso = userWithRole('aso');
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($aso)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertForbidden();
});

test('aso can view the agenda read-only', function () {
    $aso = userWithRole('aso');

    $this->actingAs($aso)->get('/agenda')->assertOk();
});

test('odontoiatra cannot update another operators appointment', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->create([
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
    $odontoiatra = userWithRole('odontoiatra');
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->create([
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

<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;

test('an appointment can be created with an assistant assigned', function () {
    $admin = userWithRole('admin');
    $odontoiatra = userWithRole('odontoiatra');
    $aso = userWithRole('aso');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'assistant_id' => $aso->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Appointment::where('patient_id', $patient->id)->first()->assistant_id)->toBe($aso->id);
});

test('the agenda operator filter also matches appointments where the operator is the assistant', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $aso = userWithRole('aso');
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->create([
        'operator_id' => $odontoiatra->id,
        'assistant_id' => $aso->id,
        'patient_id' => $patient->id,
        'start_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(10, 30),
    ]);

    $response = $this->actingAs($aso)->get(
        '/agenda?view=day&date='.now()->addDay()->toDateString().'&operator_id='.$aso->id,
    );

    $response->assertInertia(fn ($page) => $page
        ->has('appointments', 1)
        ->where('appointments.0.id', $appointment->id)
    );
});

test('an assistant double-booked across two dentists at the same time is rejected', function () {
    $admin = userWithRole('admin');
    $odontoiatraA = userWithRole('odontoiatra');
    $odontoiatraB = userWithRole('odontoiatra');
    $aso = userWithRole('aso');
    $patientA = Patient::factory()->create();
    $patientB = Patient::factory()->create();

    Appointment::factory()->create([
        'operator_id' => $odontoiatraA->id,
        'assistant_id' => $aso->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatraB->id,
        'assistant_id' => $aso->id,
        'start_at' => '2027-01-10 10:15:00',
        'end_at' => '2027-01-10 10:45:00',
    ]);

    $response->assertInvalid(['assistant_overlap']);
    expect(Appointment::where('patient_id', $patientB->id)->count())->toBe(0);
});

test('an appointment without an assistant is not blocked by overlap checks', function () {
    $admin = userWithRole('admin');
    $odontoiatraA = userWithRole('odontoiatra');
    $odontoiatraB = userWithRole('odontoiatra');
    $aso = userWithRole('aso');
    $patientA = Patient::factory()->create();
    $patientB = Patient::factory()->create();

    // aso is busy assisting odontoiatraA in this slot...
    Appointment::factory()->create([
        'operator_id' => $odontoiatraA->id,
        'assistant_id' => $aso->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    // ...but a new overlapping appointment for a different dentist with NO
    // assistant assigned must not be blocked by aso's unrelated booking.
    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatraB->id,
        'start_at' => '2027-01-10 10:15:00',
        'end_at' => '2027-01-10 10:45:00',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Appointment::where('patient_id', $patientB->id)->exists())->toBeTrue();
});

test('the same person cannot be both operator and assistant on the same appointment', function () {
    $admin = userWithRole('admin');
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'assistant_id' => $odontoiatra->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertInvalid(['assistant_id']);
});

test('a person cannot be assigned as operator while already busy as assistant elsewhere (cross-role overlap)', function () {
    $admin = userWithRole('admin');
    $odontoiatraA = userWithRole('odontoiatra');
    // This user is normally an "odontoiatra" but here plays the assistant
    // role on another dentist's appointment — nothing in the schema stops
    // that, so the overlap check must still catch it (see the cross-role
    // edge case discussed before implementing).
    $odontoiatraB = userWithRole('odontoiatra');
    $patientA = Patient::factory()->create();
    $patientB = Patient::factory()->create();

    Appointment::factory()->create([
        'operator_id' => $odontoiatraA->id,
        'assistant_id' => $odontoiatraB->id,
        'patient_id' => $patientA->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patientB->id,
        'operator_id' => $odontoiatraB->id,
        'start_at' => '2027-01-10 10:15:00',
        'end_at' => '2027-01-10 10:45:00',
    ]);

    $response->assertInvalid(['overlap']);
});

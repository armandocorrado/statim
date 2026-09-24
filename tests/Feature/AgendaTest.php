<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;

test('odontoiatra sees only their own appointments in the agenda', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $ownAppointment = Appointment::factory()->create([
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patient->id,
        'start_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(10, 30),
    ]);
    Appointment::factory()->create([
        'operator_id' => $igienista->id,
        'patient_id' => $patient->id,
        'start_at' => now()->addDay()->setTime(11, 0),
        'end_at' => now()->addDay()->setTime(11, 30),
    ]);

    $response = $this->actingAs($odontoiatra)->get('/agenda?view=day&date='.now()->addDay()->toDateString());

    $response->assertInertia(fn ($page) => $page
        ->component('Agenda/Index')
        ->has('appointments', 1)
        ->where('appointments.0.id', $ownAppointment->id)
        ->where('canViewAll', false)
        // Senza agenda.view.all la griglia non deve mostrare una colonna
        // vuota per ogni collega dello studio — solo la propria, coerente
        // con gli appuntamenti già filtrati sopra. `operators` (usato dal
        // modale per assegnare un ASO) resta invece l'elenco completo.
        ->has('visibleOperators', 1)
        ->where('visibleOperators.0.id', $odontoiatra->id)
        ->has('operators', 2)
    );
});

test('segreteria sees every operators appointments in the agenda', function () {
    $segreteria = userWithRole('segreteria');
    $odontoiatra = userWithRole('odontoiatra');
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    Appointment::factory()->create([
        'operator_id' => $odontoiatra->id,
        'patient_id' => $patient->id,
        'start_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(10, 30),
    ]);
    Appointment::factory()->create([
        'operator_id' => $igienista->id,
        'patient_id' => $patient->id,
        'start_at' => now()->addDay()->setTime(11, 0),
        'end_at' => now()->addDay()->setTime(11, 30),
    ]);

    $response = $this->actingAs($segreteria)->get('/agenda?view=day&date='.now()->addDay()->toDateString());

    $response->assertInertia(fn ($page) => $page
        ->has('appointments', 2)
        ->where('canViewAll', true)
        // segreteria stessa ha agenda.view.all, quindi compare anche lei
        // nell'elenco operatori (3: odontoiatra, igienista, segreteria).
        ->has('visibleOperators', 3)
    );
});

test('the appointment carries enough data to open the patient sheet without a second lookup', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
    Appointment::factory()->create([
        'operator_id' => $admin->id,
        'patient_id' => $patient->id,
        'start_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(10, 30),
    ]);

    $response = $this->actingAs($admin)->get('/agenda?view=day&date='.now()->addDay()->toDateString());

    $response->assertInertia(fn ($page) => $page
        ->where('appointments.0.patient.id', $patient->id)
        ->where('appointments.0.patient.first_name', 'Mario')
    );
});

test('patient search requires at least two characters', function () {
    $admin = userWithRole('admin');
    Patient::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);

    $this->actingAs($admin)->getJson('/agenda/patients-search?q=m')
        ->assertOk()
        ->assertJsonCount(0);

    $this->actingAs($admin)->getJson('/agenda/patients-search?q=mario')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['first_name' => 'Mario']);
});

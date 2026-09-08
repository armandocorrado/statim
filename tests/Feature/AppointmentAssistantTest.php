<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;

test('an appointment can be created with an assistant assigned', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $aso = userForTenant($tenant, 'aso');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

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

test('an assistant from another tenant is rejected', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $admin = userForTenant($tenantA, 'admin');
    $odontoiatra = userForTenant($tenantA, 'odontoiatra');
    $asoFromOtherTenant = userForTenant($tenantB, 'aso');
    $patient = Patient::factory()->create(['tenant_id' => $tenantA->id]);

    $response = $this->actingAs($admin)->post('/agenda/appointments', [
        'patient_id' => $patient->id,
        'operator_id' => $odontoiatra->id,
        'assistant_id' => $asoFromOtherTenant->id,
        'start_at' => '2027-01-10 10:00:00',
        'end_at' => '2027-01-10 10:30:00',
    ]);

    $response->assertInvalid(['assistant_id']);
});

test('the agenda operator filter also matches appointments where the operator is the assistant', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $aso = userForTenant($tenant, 'aso');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
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

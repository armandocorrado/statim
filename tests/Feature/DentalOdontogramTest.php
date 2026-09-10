<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalToothCondition;

test('odontoiatra can open the odontogram', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/Odontogram')
            ->where('canManage', true)
            ->has('permanentTeeth', 32)
            ->has('deciduousTeeth', 20)
        );
});

test('admin can open the odontogram', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertOk();
});

test('igienista cannot open the odontogram', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertForbidden();
});

test('segreteria and aso cannot open the odontogram', function () {
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    foreach (['segreteria', 'aso'] as $role) {
        $user = userForTenant($tenant, $role);

        $this->actingAs($user)->get("/patients/{$patient->id}/dental/odontogram")
            ->assertForbidden();
    }
});

test('the odontogram cannot be opened across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $odontoiatraA = userForTenant($tenantA, 'odontoiatra');
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($odontoiatraA)->get("/patients/{$patientB->id}/dental/odontogram")
        ->assertForbidden();
});

test('opening the odontogram is recorded in the audit log', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram");

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'odontogram_viewed',
        'auditable_type' => Patient::class,
        'auditable_id' => $patient->id,
        'user_id' => $odontoiatra->id,
    ]);
});

test('odontoiatra can record a tooth condition', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
        'notes' => 'Carie occlusale',
    ]);

    $response->assertSessionHasNoErrors();
    $condition = DentalToothCondition::where('patient_id', $patient->id)->firstOrFail();
    expect($condition->tooth_number)->toBe('16')
        ->and($condition->condition_type)->toBe(\App\Modules\Dental\Enums\ToothCondition::Carious)
        ->and($condition->operator_id)->toBe($odontoiatra->id)
        ->and($condition->notes)->toBe('Carie occlusale');
});

test('an invalid tooth number is rejected', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '99',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
    ])->assertInvalid(['tooth_number']);
});

test('a deciduous tooth number is accepted', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '55',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(DentalToothCondition::where('patient_id', $patient->id)->where('tooth_number', '55')->exists())->toBeTrue();
});

test('igienista cannot record a tooth condition', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
    ])->assertForbidden();

    expect(DentalToothCondition::where('patient_id', $patient->id)->count())->toBe(0);
});

test('a tooth condition has no update or delete route', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $condition = DentalToothCondition::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/odontogram/{$condition->id}")
        ->assertStatus(404);
    $this->actingAs($odontoiatra)->delete("/patients/{$patient->id}/dental/odontogram/{$condition->id}")
        ->assertStatus(404);
});

test('the current state of a tooth is the most recent record regardless of condition type', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    DentalToothCondition::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'carious',
        'recorded_date' => now()->subDays(30)->toDateString(),
    ]);
    DentalToothCondition::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
    ]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page
            ->where('currentStates.26.condition_type', 'filled')
        );
});

test('full history of a tooth remains available even after a newer state is recorded', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    DentalToothCondition::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'carious',
        'recorded_date' => now()->subDays(30)->toDateString(),
    ]);
    DentalToothCondition::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
    ]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page->has('history', 2));
});

test('a tooth condition can be linked to an existing diary entry', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $diaryEntry = DentalDiaryEntry::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
        'diary_entry_id' => $diaryEntry->id,
    ])->assertSessionHasNoErrors();

    $condition = DentalToothCondition::where('patient_id', $patient->id)->firstOrFail();
    expect($condition->diary_entry_id)->toBe($diaryEntry->id);
});

test('a diary entry from another patient cannot be linked', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $otherPatient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $diaryEntry = DentalDiaryEntry::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $otherPatient->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
        'diary_entry_id' => $diaryEntry->id,
    ])->assertInvalid(['diary_entry_id']);
});

test('notes on a tooth condition are encrypted at rest', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
        'notes' => 'Nota clinica sensibile',
    ]);

    $condition = DentalToothCondition::where('patient_id', $patient->id)->firstOrFail();
    $raw = \Illuminate\Support\Facades\DB::table('dental_tooth_conditions')->where('id', $condition->id)->first();

    expect($raw->notes)->not->toBe('Nota clinica sensibile')
        ->and($condition->notes)->toBe('Nota clinica sensibile');
});

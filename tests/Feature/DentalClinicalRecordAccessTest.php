<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalAnamnesis;
use Illuminate\Support\Facades\DB;

test('segreteria cannot open the clinical record', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($segreteria)->get("/patients/{$patient->id}/dental")
        ->assertForbidden();
});

test('aso cannot open the clinical record', function () {
    $tenant = Tenant::factory()->create();
    $aso = userForTenant($tenant, 'aso');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($aso)->get("/patients/{$patient->id}/dental")
        ->assertForbidden();
});

test('odontoiatra can open the clinical record with full access', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/ClinicalRecord')
            ->where('hasFullAccess', true)
        );
});

test('igienista can open the clinical record with partial access', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/ClinicalRecord')
            ->where('hasFullAccess', false)
        );
});

test('admin can open the clinical record with full access', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->get("/patients/{$patient->id}/dental")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasFullAccess', true));
});

test('opening the clinical record is recorded in the audit log', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental");

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'clinical_record_viewed',
        'auditable_type' => Patient::class,
        'auditable_id' => $patient->id,
        'user_id' => $odontoiatra->id,
    ]);
});

test('the clinical record cannot be opened across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $odontoiatraA = userForTenant($tenantA, 'odontoiatra');
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($odontoiatraA)->get("/patients/{$patientB->id}/dental")
        ->assertForbidden();
});

test('anamnesis content is encrypted at rest', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/anamnesis", [
        'pathologies' => 'Diabete di tipo 2',
    ]);

    $anamnesis = DentalAnamnesis::where('patient_id', $patient->id)->firstOrFail();
    $raw = DB::table('dental_anamneses')->where('id', $anamnesis->id)->first();

    expect($raw->pathologies)->not->toBe('Diabete di tipo 2')
        ->and($anamnesis->pathologies)->toBe('Diabete di tipo 2');
});

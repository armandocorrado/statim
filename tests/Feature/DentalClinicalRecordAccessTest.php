<?php

use App\Core\Patients\Models\Patient;
use App\Modules\Dental\Models\DentalAnamnesis;
use Illuminate\Support\Facades\DB;

test('segreteria cannot open the clinical record', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();

    $this->actingAs($segreteria)->get("/patients/{$patient->id}/dental")
        ->assertForbidden();
});

test('aso cannot open the clinical record', function () {
    $aso = userWithRole('aso');
    $patient = Patient::factory()->create();

    $this->actingAs($aso)->get("/patients/{$patient->id}/dental")
        ->assertForbidden();
});

test('odontoiatra can open the clinical record with full access', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/ClinicalRecord')
            ->where('hasFullAccess', true)
        );
});

test('igienista can open the clinical record with partial access', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/ClinicalRecord')
            ->where('hasFullAccess', false)
        );
});

test('admin can open the clinical record with full access', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();

    $this->actingAs($admin)->get("/patients/{$patient->id}/dental")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasFullAccess', true));
});

test('opening the clinical record is recorded in the audit log', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental");

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'clinical_record_viewed',
        'auditable_type' => Patient::class,
        'auditable_id' => $patient->id,
        'user_id' => $odontoiatra->id,
    ]);
});

test('anamnesis content is encrypted at rest', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/anamnesis", [
        'pathologies' => 'Diabete di tipo 2',
    ]);

    $anamnesis = DentalAnamnesis::where('patient_id', $patient->id)->firstOrFail();
    $raw = DB::table('dental_anamneses')->where('id', $anamnesis->id)->first();

    expect($raw->pathologies)->not->toBe('Diabete di tipo 2')
        ->and($anamnesis->pathologies)->toBe('Diabete di tipo 2');
});

<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Modules\Dental\Models\DentalTreatmentPlan;

test('a treatment plan cannot be accessed across tenants (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('odontoiatra');
    $patientB = Patient::factory()->create();
    $planB = DentalTreatmentPlan::factory()->create(['patient_id' => $patientB->id]);
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('odontoiatra');

    $this->actingAs($studioA['user'])->get("/patients/{$patientB->id}/dental/treatment-plans/{$planB->id}")
        ->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

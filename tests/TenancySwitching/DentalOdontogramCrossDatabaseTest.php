<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Support\TenantConnectionResolver;

test('the odontogram cannot be opened across tenants (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('odontoiatra');
    $patientB = Patient::factory()->create();
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('odontoiatra');

    $this->actingAs($studioA['user'])->get("/patients/{$patientB->id}/dental/odontogram")
        ->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

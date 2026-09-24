<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Support\TenantConnectionResolver;

test('a guardian must belong to the same studio\'s physical database', function () {
    $studioB = provisionRealStudioWithRole('admin');
    $guardianInOtherStudio = Patient::factory()->create();
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('admin');

    $response = $this->actingAs($studioA['user'])->post('/patients', [
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'guardian_patient_id' => $guardianInOtherStudio->id,
    ]);

    $response->assertInvalid(['guardian_patient_id']);

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

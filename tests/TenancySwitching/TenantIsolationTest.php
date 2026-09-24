<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;

/**
 * Provisiona uno studio reale (file sqlite dedicato) con un admin e un
 * paziente, lasciando la connessione 'tenant' RISOLTA su di esso al
 * ritorno (a differenza di provisionLoginTestTenant, che la rilascia) —
 * qui serve restare connessi perche' il chiamante user actingAs() subito
 * dopo, che salta la sessione e quindi non fa scattare
 * RestoreTenantConnection da solo.
 *
 * @return array{tenant: \App\Core\Tenancy\Models\Tenant, admin: User, patient: Patient, path: string}
 */
function provisionIsolatedStudio(string $patientLastName): array
{
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = \App\Core\Tenancy\Models\Tenant::factory()->create(['database_name' => $path]);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant', '--path' => 'database/migrations', '--force' => true,
    ]);

    TenantRoleProvisioner::provisionDefaults();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $patient = Patient::factory()->create(['last_name' => $patientLastName]);

    return ['tenant' => $tenant, 'admin' => $admin, 'patient' => $patient, 'path' => $path];
}

test('a user only sees patients from their own studio, never another studio\'s (real separate databases)', function () {
    $studioA = provisionIsolatedStudio('RossiVisibile');
    app(TenantConnectionResolver::class)->release();

    $studioB = provisionIsolatedStudio('BianchiNascosto');
    app(TenantConnectionResolver::class)->release();

    // Torniamo esplicitamente sullo studio A per la request: actingAs()
    // salta la sessione (e quindi RestoreTenantConnection), quindi la
    // connessione va risolta a mano prima della richiesta.
    app(TenantConnectionResolver::class)->forTenant($studioA['tenant']);

    $response = $this->actingAs($studioA['admin'])->get('/patients');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('patients.data', 1)
        ->where('patients.data.0.last_name', 'RossiVisibile')
    );

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

test('every write to a patient is recorded in the audit log', function () {
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = \App\Core\Tenancy\Models\Tenant::factory()->create(['database_name' => $path]);
    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant', '--path' => 'database/migrations', '--force' => true,
    ]);

    TenantRoleProvisioner::provisionDefaults();
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)->post('/patients', [
        'first_name' => 'Mario',
        'last_name' => 'Verdi',
    ]);

    $patient = Patient::first();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'created',
        'auditable_id' => $patient->id,
    ], 'tenant');

    $resolver->release();
    unlink($path);
});

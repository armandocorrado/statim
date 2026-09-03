<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;

function userForTenant(Tenant $tenant, string $role = 'admin'): User
{
    TenantRoleProvisioner::provisionDefaults($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

test('a user only sees patients belonging to their own tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = userForTenant($tenantA);
    userForTenant($tenantB);

    Patient::factory()->create(['tenant_id' => $tenantA->id, 'last_name' => 'RossiVisibile']);
    Patient::factory()->create(['tenant_id' => $tenantB->id, 'last_name' => 'BianchiNascosto']);

    $response = $this->actingAs($userA)->get('/patients');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('patients.data', 1)
        ->where('patients.data.0.last_name', 'RossiVisibile')
    );
});

test('a user cannot view another tenant patient by forcing the id in the url', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = userForTenant($tenantA);
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($userA)->get("/patients/{$patientB->id}");

    $response->assertForbidden();
});

test('a user cannot update another tenant patient by forcing the id in the url', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = userForTenant($tenantA);
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id, 'first_name' => 'Original']);

    $response = $this->actingAs($userA)->put("/patients/{$patientB->id}", [
        'first_name' => 'Hacked',
        'last_name' => $patientB->last_name,
    ]);

    $response->assertForbidden();
    expect($patientB->fresh()->first_name)->toBe('Original');
});

test('a newly created patient is automatically attached to the authenticated user tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = userForTenant($tenantA);

    $this->actingAs($userA)->post('/patients', [
        'first_name' => 'Mario',
        'last_name' => 'Verdi',
    ]);

    $patient = Patient::first();

    expect($patient->tenant_id)->toBe($tenantA->id)
        ->and(Patient::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count())->toBe(0);
});

test('every write to a patient is recorded in the audit log for the correct tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = userForTenant($tenant);

    $this->actingAs($user)->post('/patients', [
        'first_name' => 'Mario',
        'last_name' => 'Verdi',
    ]);

    $patient = Patient::first();

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'action' => 'created',
        'auditable_id' => $patient->id,
    ]);
});

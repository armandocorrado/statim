<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Models\Invitation;
use App\Models\User;

test('admin can invite a new user', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');

    $response = $this->actingAs($admin)->post('/users/invitations', [
        'email' => 'nuovo@rossi.test',
        'role' => 'segreteria',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('invitations', [
        'tenant_id' => $tenant->id,
        'email' => 'nuovo@rossi.test',
        'role' => 'segreteria',
        'invited_by' => $admin->id,
    ]);
});

test('segreteria cannot invite a new user', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');

    $response = $this->actingAs($segreteria)->post('/users/invitations', [
        'email' => 'nuovo@rossi.test',
        'role' => 'segreteria',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('invitations', ['email' => 'nuovo@rossi.test']);
});

test('inviting an email that already belongs to a user is rejected', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $existing = User::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($admin)->post('/users/invitations', [
        'email' => $existing->email,
        'role' => 'segreteria',
    ]);

    $response->assertInvalid(['email']);
});

test('inviting an email with a pending unexpired invitation is rejected', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'pending@rossi.test',
        'invited_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post('/users/invitations', [
        'email' => 'pending@rossi.test',
        'role' => 'segreteria',
    ]);

    $response->assertInvalid(['email']);
});

test('admin can revoke a pending invitation only for their own tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');
    $invitationB = Invitation::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($adminA)->delete("/users/invitations/{$invitationB->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('invitations', ['id' => $invitationB->id]);

    $invitationA = Invitation::factory()->create(['tenant_id' => $tenantA->id]);
    $response = $this->actingAs($adminA)->delete("/users/invitations/{$invitationA->id}");

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseMissing('invitations', ['id' => $invitationA->id]);
});

test('changing a user role is recorded in the audit log without leaking the password', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $target = userForTenant($tenant, 'segreteria');

    $response = $this->actingAs($admin)->patch("/users/{$target->id}/role", [
        'role' => 'dentista',
    ]);

    $response->assertRedirect(route('users.index'));
    expect($target->fresh()->getRoleNames()->all())->toBe(['dentista']);

    $log = AuditLog::where('action', 'role_changed')
        ->where('auditable_id', $target->id)
        ->firstOrFail();

    expect($log->new_values)->toBe(['role' => ['dentista']])
        ->and($log->old_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('password');
});

test('admin cannot deactivate their own account', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');

    $response = $this->actingAs($admin)->patch("/users/{$admin->id}/deactivate");

    $response->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('admin can deactivate and reactivate another user', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $target = userForTenant($tenant, 'segreteria');

    $this->actingAs($admin)->patch("/users/{$target->id}/deactivate")
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/users/{$target->id}/reactivate")
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeTrue();
});

test('the users nav link is only shared for a user with the users.view permission', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $segreteria = userForTenant($tenant, 'segreteria');

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('users.view')));

    $this->actingAs($segreteria)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('users.view')));
});

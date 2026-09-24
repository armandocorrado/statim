<?php

use App\Core\Audit\Models\AuditLog;
use App\Models\User;

test('a valid invitation token creates and logs in an active user with the invited role', function () {
    ['tenant' => $tenant, 'invitation' => $invitation, 'token' => $token] = provisionInvitationTestTenant([
        'email' => 'invitato@rossi.test',
        'role' => 'segreteria',
    ]);

    $response = $this->post("/invitations/{$tenant->id}/{$token}", [
        'name' => 'Mario Rossi',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'invitato@rossi.test')->firstOrFail();

    expect($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['segreteria']);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    $log = AuditLog::where('action', 'role_assigned')
        ->where('auditable_id', $user->id)
        ->firstOrFail();

    expect($log->user_id)->toBeNull()
        ->and($log->new_values)->toBe(['role' => ['segreteria']]);
});

test('an expired invitation token is rejected', function () {
    ['tenant' => $tenant, 'token' => $token] = provisionInvitationTestTenant([
        'email' => 'invitato@rossi.test',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->post("/invitations/{$tenant->id}/{$token}", [
        'name' => 'Mario Rossi',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(410);
    $this->assertGuest();
});

test('an already accepted invitation token cannot be reused', function () {
    ['tenant' => $tenant, 'token' => $token] = provisionInvitationTestTenant([
        'email' => 'invitato@rossi.test',
        'accepted_at' => now(),
    ]);

    $response = $this->post("/invitations/{$tenant->id}/{$token}", [
        'name' => 'Mario Rossi',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(410);
    $this->assertGuest();
});

test('a deactivated user cannot log in even with correct credentials', function () {
    ['user' => $user] = provisionLoginTestTenant(userAttrs: ['is_active' => false]);

    $this->post(route('login.identify'), ['email' => $user->email]);

    $response = $this->post('/login', ['password' => 'password']);

    $response->assertInvalid(['password']);
    $this->assertGuest();
});

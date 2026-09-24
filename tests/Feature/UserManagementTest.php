<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Users\Models\Invitation;
use App\Models\User;

test('admin can invite a new user', function () {
    $admin = userWithRole('admin');

    $response = $this->actingAs($admin)->post('/users/invitations', [
        'email' => 'nuovo@rossi.test',
        'role' => 'segreteria',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('invitations', [
        'email' => 'nuovo@rossi.test',
        'role' => 'segreteria',
        'invited_by' => $admin->id,
    ]);
});

test('segreteria cannot invite a new user', function () {
    $segreteria = userWithRole('segreteria');

    $response = $this->actingAs($segreteria)->post('/users/invitations', [
        'email' => 'nuovo@rossi.test',
        'role' => 'segreteria',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('invitations', ['email' => 'nuovo@rossi.test']);
});

test('inviting an email that already belongs to a user is rejected', function () {
    $admin = userWithRole('admin');
    $existing = User::factory()->create();

    $response = $this->actingAs($admin)->post('/users/invitations', [
        'email' => $existing->email,
        'role' => 'segreteria',
    ]);

    $response->assertInvalid(['email']);
});

test('inviting an email with a pending unexpired invitation is rejected', function () {
    $admin = userWithRole('admin');
    Invitation::factory()->create([
        'email' => 'pending@rossi.test',
        'invited_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post('/users/invitations', [
        'email' => 'pending@rossi.test',
        'role' => 'segreteria',
    ]);

    $response->assertInvalid(['email']);
});

test('admin can revoke a pending invitation for their own studio', function () {
    $admin = userWithRole('admin');
    $invitation = Invitation::factory()->create();

    $response = $this->actingAs($admin)->delete("/users/invitations/{$invitation->id}");

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
});

test('changing a user role is recorded in the audit log without leaking the password', function () {
    $admin = userWithRole('admin');
    $target = userWithRole('segreteria');

    $response = $this->actingAs($admin)->patch("/users/{$target->id}/role", [
        'role' => 'admin',
    ]);

    $response->assertRedirect(route('users.index'));
    expect($target->fresh()->getRoleNames()->all())->toBe(['admin']);

    $log = AuditLog::where('action', 'role_changed')
        ->where('auditable_id', $target->id)
        ->firstOrFail();

    expect($log->new_values)->toBe(['role' => ['admin']])
        ->and($log->old_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('password');
});

test('admin cannot deactivate their own account', function () {
    $admin = userWithRole('admin');

    $response = $this->actingAs($admin)->patch("/users/{$admin->id}/deactivate");

    $response->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('admin can deactivate and reactivate another user', function () {
    $admin = userWithRole('admin');
    $target = userWithRole('segreteria');

    $this->actingAs($admin)->patch("/users/{$target->id}/deactivate")
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/users/{$target->id}/reactivate")
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeTrue();
});

test('the users nav link is only shared for a user with the users.view permission', function () {
    $admin = userWithRole('admin');
    $segreteria = userWithRole('segreteria');

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('users.view')));

    $this->actingAs($segreteria)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('users.view')));
});

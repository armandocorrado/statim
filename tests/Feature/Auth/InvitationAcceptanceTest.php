<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Models\Invitation;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;
use Illuminate\Support\Str;

function createInvitationWithToken(Tenant $tenant, array $overrides = []): array
{
    TenantRoleProvisioner::provisionDefaults($tenant);

    $plainTextToken = Str::random(40);

    $invitation = Invitation::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'email' => 'invitato@rossi.test',
        'role' => 'collaboratore',
        'token_hash' => hash('sha256', $plainTextToken),
        'expires_at' => now()->addDays(7),
        'accepted_at' => null,
    ], $overrides));

    return [$invitation, $plainTextToken];
}

test('a valid invitation token creates and logs in an active user with the invited role', function () {
    $tenant = Tenant::factory()->create();
    [$invitation, $token] = createInvitationWithToken($tenant);

    $response = $this->post("/invitations/{$token}", [
        'name' => 'Mario Rossi',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'invitato@rossi.test')->firstOrFail();

    expect($user->tenant_id)->toBe($tenant->id)
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['collaboratore']);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an expired invitation token is rejected', function () {
    $tenant = Tenant::factory()->create();
    [, $token] = createInvitationWithToken($tenant, ['expires_at' => now()->subDay()]);

    $response = $this->post("/invitations/{$token}", [
        'name' => 'Mario Rossi',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(410);
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'invitato@rossi.test']);
});

test('an already accepted invitation token cannot be reused', function () {
    $tenant = Tenant::factory()->create();
    [, $token] = createInvitationWithToken($tenant, ['accepted_at' => now()]);

    $response = $this->post("/invitations/{$token}", [
        'name' => 'Mario Rossi',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(410);
    $this->assertGuest();
});

test('a deactivated user cannot log in even with correct credentials', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertInvalid(['email']);
    $this->assertGuest();
});

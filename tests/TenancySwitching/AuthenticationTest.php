<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('step', 'email'));
});

test('users can authenticate through the two-step flow when their email belongs to a single studio', function () {
    ['user' => $user] = provisionLoginTestTenant();

    $this->post(route('login.identify'), ['email' => $user->email])
        ->assertRedirect(route('login'));

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('step', 'password'));

    $response = $this->post('/login', ['password' => 'password']);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    ['user' => $user] = provisionLoginTestTenant();

    $this->post(route('login.identify'), ['email' => $user->email]);

    $this->post('/login', ['password' => 'wrong-password']);

    $this->assertGuest();
});

test('an unknown email is rejected with the same generic error as a wrong password', function () {
    $response = $this->post(route('login.identify'), ['email' => 'nobody@example.test']);

    $response->assertInvalid(['email']);
    $this->assertGuest();

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('step', 'email'));
});

test('an email present in two studios shows a studio picker and logs into the chosen one', function () {
    $email = 'dentista.itinerante@example.test';

    ['tenant' => $tenantA, 'user' => $userA] = provisionLoginTestTenant(
        ['name' => 'Studio A'],
        ['email' => $email, 'password' => bcrypt('password-a')],
    );
    ['tenant' => $tenantB, 'user' => $userB] = provisionLoginTestTenant(
        ['name' => 'Studio B'],
        ['email' => $email, 'password' => bcrypt('password-b')],
    );

    $this->post(route('login.identify'), ['email' => $email])
        ->assertRedirect(route('login'));

    $this->get('/login')->assertInertia(
        fn (Assert $page) => $page->component('Auth/Login')
            ->where('step', 'choose-studio')
            ->has('studios', 2)
    );

    $this->post(route('login.select-studio'), ['tenant_id' => $tenantB->id])
        ->assertRedirect(route('login'));

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('step', 'password'));

    $response = $this->post('/login', ['password' => 'password-b']);

    $this->assertAuthenticatedAs($userB);
    $response->assertRedirect(route('dashboard', absolute: false));

    expect($userA->id)->not->toBe($userB->id);
});

test('choosing a studio not among the candidates is rejected', function () {
    $email = 'dentista.itinerante2@example.test';

    ['tenant' => $tenantA] = provisionLoginTestTenant(['name' => 'Studio A'], ['email' => $email]);
    provisionLoginTestTenant(['name' => 'Studio B'], ['email' => $email]);
    ['tenant' => $otherTenant] = provisionLoginTestTenant();

    $this->post(route('login.identify'), ['email' => $email]);

    $response = $this->post(route('login.select-studio'), ['tenant_id' => $otherTenant->id]);

    $response->assertInvalid(['tenant_id']);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('step', 'choose-studio'));

    expect($tenantA)->not->toBeNull();
});

test('a deactivated user cannot log in even with correct credentials', function () {
    ['user' => $user] = provisionLoginTestTenant(userAttrs: ['is_active' => false]);

    $this->post(route('login.identify'), ['email' => $user->email]);

    $response = $this->post('/login', ['password' => 'password']);

    $response->assertInvalid(['password']);
    $this->assertGuest();
});

test('users can logout', function () {
    ['user' => $user] = provisionLoginTestTenant();

    $this->post(route('login.identify'), ['email' => $user->email]);
    $this->post('/login', ['password' => 'password']);

    $response = $this->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('a plain user factory create without tenant_users mapping cannot log in at all', function () {
    // Studio reale (file sqlite dedicato, non ':memory:' condiviso — questa
    // TestCase esclude 'tenant' dal rollback automatico apposta, vedi
    // TenancySwitchingTestCase) con un utente creato, ma SENZA la riga
    // tenant_users corrispondente: deve restare irraggiungibile dal login.
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = Tenant::factory()->create(['database_name' => $path]);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);

    $user = User::factory()->create();

    $resolver->release();

    $response = $this->post(route('login.identify'), ['email' => $user->email]);

    $response->assertInvalid(['email']);

    unlink($path);
});

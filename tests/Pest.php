<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Provisiona uno studio di prova REALE per i test del login a due step
 * (Tappa 2): un file sqlite temporaneo (non ':memory:' - ogni connessione
 * ':memory:' e' un DB isolato per suo conto, non riutilizzabile per
 * simulare "un altro studio" nello stesso test), schema di dominio
 * migrato, un utente e la riga tenant_users corrispondente. Stesso schema
 * (tenant + utente + mappa centrale) del comando tenant:seed-test-studio,
 * qui in forma leggera per i test.
 *
 * @return array{tenant: \App\Core\Tenancy\Models\Tenant, user: \App\Models\User, path: string}
 */
function provisionLoginTestTenant(array $tenantAttrs = [], array $userAttrs = []): array
{
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = \App\Core\Tenancy\Models\Tenant::factory()->create(array_merge(
        ['database_name' => $path],
        $tenantAttrs,
    ));

    $resolver = app(\App\Core\Tenancy\Support\TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    $user = \App\Models\User::factory()->create(array_merge(
        ['tenant_id' => $tenant->id],
        $userAttrs,
    ));

    \App\Core\Tenancy\Models\TenantUser::create([
        'email' => $user->email,
        'tenant_id' => $tenant->id,
        'remote_user_id' => $user->id,
        'is_active' => true,
    ]);

    $resolver->release();

    return ['tenant' => $tenant, 'user' => $user, 'path' => $path];
}

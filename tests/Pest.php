<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenancySwitchingTestCase;
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
| tests/TenancySwitching e' una suite separata (non sotto tests/Feature,
| Pest non ammette due binding diversi sullo stesso file/cartella): quei
| test chiamano TenantConnectionResolver esplicitamente e non possono
| condividere il rollback automatico di 'tenant' con il resto della suite
| — vedi Tests\TenancySwitchingTestCase per il perche'.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TenancySwitchingTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('TenancySwitching');

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
 * Crea un utente con un ruolo, sulla connessione 'tenant' CORRENTE (di
 * default ':memory:' condiviso nei test — non serve un Tenant reale per la
 * stragrande maggioranza dei test, che non testano davvero l'isolamento
 * multi-DB: un solo studio implicito per test basta). Idempotente sul
 * catalogo permessi (TenantRoleProvisioner::provisionDefaults() usa
 * findOrCreate/syncPermissions).
 *
 * Imposta anche 'tenant' come connessione di DEFAULT dell'app: in
 * produzione questo lo fa RestoreTenantConnection leggendo session('tenant_id'),
 * ma actingAs() nei test salta la sessione (imposta l'utente direttamente
 * sul guard) e quel middleware non scatta mai. Senza questa riga, Rule::exists()/
 * Rule::unique() nelle FormRequest (query dirette via DB::table(), non un
 * model — non seguono UsesTenantConnection) risolverebbero sulla
 * connessione di default sbagliata.
 *
 * Stessa ragione per setCurrentForTesting(): codice applicativo come
 * UserController::storeInvitation() legge TenantConnectionResolver::current()
 * per sapere "di quale studio è questa richiesta" (in produzione lo imposta
 * sempre RestoreTenantConnection), e actingAs() lo lascerebbe altrimenti
 * null. Un solo Tenant "fittizio" per test — creato alla prima chiamata,
 * riusato dalle successive nello stesso test — cosi' piu' utenti dello
 * stesso test restano coerentemente nello stesso studio implicito.
 */
function userWithRole(string $role = 'admin'): \App\Models\User
{
    \Illuminate\Support\Facades\DB::setDefaultConnection('tenant');

    $resolver = app(\App\Core\Tenancy\Support\TenantConnectionResolver::class);
    if (! $resolver->current()) {
        $resolver->setCurrentForTesting(\App\Core\Tenancy\Models\Tenant::factory()->create());
    }

    \App\Core\Users\Support\TenantRoleProvisioner::provisionDefaults();

    $user = \App\Models\User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * TenantDatabaseCreator::nameFor() produce un percorso FISSO per slug in
 * ambiente sqlite (non ':memory:', serve un file vero) - un file cosi'
 * sopravvive tra un test e l'altro (anche tra file di test diversi), e
 * DatabaseSeeder::createStudio() non e' idempotente sulla creazione utenti
 * (mai stato pensato per essere rilanciato piu' volte sullo stesso DB,
 * nemmeno nel vecchio schema). Da chiamare prima di ogni test che invoca
 * DatabaseSeeder::run(), per ripartire da un file pulito.
 */
function cleanupDemoStudioFiles(): void
{
    foreach (['studio-rossi', 'studio-bianchi'] as $slug) {
        $path = \App\Core\Tenancy\Support\TenantDatabaseCreator::nameFor($slug);

        if ($path !== ':memory:' && file_exists($path)) {
            unlink($path);
        }
    }
}

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

    $user = \App\Models\User::factory()->create($userAttrs);

    \App\Core\Tenancy\Models\TenantUser::create([
        'email' => $user->email,
        'tenant_id' => $tenant->id,
        'remote_user_id' => $user->id,
        'is_active' => true,
    ]);

    $resolver->release();

    return ['tenant' => $tenant, 'user' => $user, 'path' => $path];
}

/**
 * Come provisionLoginTestTenant, ma per testare l'accettazione di un
 * invito: uno studio di prova reale (RBAC incluso, serve per
 * assignRole() dentro InvitationAcceptController::store()) con un
 * invito pendente, nessun utente ancora creato.
 *
 * @return array{tenant: \App\Core\Tenancy\Models\Tenant, invitation: \App\Core\Users\Models\Invitation, token: string, path: string}
 */
function provisionInvitationTestTenant(array $invitationAttrs = []): array
{
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = \App\Core\Tenancy\Models\Tenant::factory()->create(['database_name' => $path]);

    $resolver = app(\App\Core\Tenancy\Support\TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    \App\Core\Users\Support\TenantRoleProvisioner::provisionDefaults();

    $plainTextToken = \Illuminate\Support\Str::random(40);

    $invitation = \App\Core\Users\Models\Invitation::factory()->create(array_merge([
        'token_hash' => hash('sha256', $plainTextToken),
    ], $invitationAttrs));

    $resolver->release();

    return ['tenant' => $tenant, 'invitation' => $invitation, 'token' => $plainTextToken, 'path' => $path];
}

/**
 * Provisiona uno studio di prova REALE (file sqlite dedicato, non
 * ':memory:') con RBAC e un utente col ruolo dato — per i test
 * cross-database della suite TenancySwitching che verificano che un ID
 * dell'altro studio non sia raggiungibile (404, non più 403: la
 * connessione stessa isola, non più un confronto tenant_id).
 *
 * A differenza di userWithRole() (pensato per il DB 'tenant' condiviso
 * ':memory:' di un singolo test Feature), qui la connessione RESTA
 * risolta su questo tenant al ritorno — serve per il lato "corrente" della
 * richiesta, che poi fa actingAs() (salta la sessione, quindi
 * RestoreTenantConnection non gira mai da solo). Non richiamare
 * $resolver->release() prima di aver fatto la richiesta HTTP del test.
 *
 * Nota: NON usare $resolver->release() seguito da userWithRole() nello
 * stesso test — release() riporta 'tenant' al valore originale (di solito
 * ':memory:' condiviso), ma DB::purge() nel frattempo ne ha già distrutto
 * il contenuto: una nuova connessione ':memory:' è sempre vuota, senza le
 * tabelle migrate all'avvio della suite.
 *
 * @return array{tenant: \App\Core\Tenancy\Models\Tenant, user: \App\Models\User, path: string}
 */
function provisionRealStudioWithRole(string $role): array
{
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = \App\Core\Tenancy\Models\Tenant::factory()->create(['database_name' => $path]);

    $resolver = app(\App\Core\Tenancy\Support\TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    \App\Core\Users\Support\TenantRoleProvisioner::provisionDefaults();

    $user = \App\Models\User::factory()->create();
    $user->assignRole($role);

    return ['tenant' => $tenant, 'user' => $user, 'path' => $path];
}

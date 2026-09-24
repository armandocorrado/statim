<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantUser;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the demo seeder provisions two separate real studios, each with one user per fixed role', function () {
    cleanupDemoStudioFiles();
    (new DatabaseSeeder)->run();

    expect(Tenant::count())->toBe(2);

    $resolver = app(TenantConnectionResolver::class);

    foreach (['rossi.test', 'bianchi.test'] as $emailDomain) {
        $tenant = Tenant::where('email', "info@{$emailDomain}")->firstOrFail();

        // Ogni studio ha il proprio database reale: distinto dagli altri,
        // non più una riga tenant_id dentro un'unica tabella condivisa.
        expect($tenant->database_name)->not->toBeNull();

        $resolver->forTenant($tenant);

        foreach (['admin', 'odontoiatra', 'igienista', 'aso', 'segreteria'] as $role) {
            $user = User::where('email', "{$role}@{$emailDomain}")->firstOrFail();

            expect($user->getRoleNames()->all())->toBe([$role]);

            expect(TenantUser::where('email', "{$role}@{$emailDomain}")->where('tenant_id', $tenant->id)->exists())
                ->toBeTrue();
        }

        // 5 dal seeding base + il pool ampliato da DemoHistoricalDataSeeder
        // (necessario per uno storico credibile sui grafici della dashboard
        // admin — vedi CLAUDE.md, sezione "Comandi utili").
        expect(Patient::count())->toBe(24);

        $resolver->release();
    }
});

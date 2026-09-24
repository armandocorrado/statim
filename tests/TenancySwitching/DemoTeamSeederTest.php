<?php

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoTeamSeeder;

test('the demo team seeder adds 4 odontoiatri, 4 igienisti and 4 aso per demo studio', function () {
    cleanupDemoStudioFiles();
    (new DatabaseSeeder)->run();

    $resolver = app(TenantConnectionResolver::class);

    foreach (['rossi.test', 'bianchi.test'] as $emailDomain) {
        $tenant = Tenant::where('email', "info@{$emailDomain}")->firstOrFail();
        $resolver->forTenant($tenant);

        $odontoiatri = User::whereHas('roles', fn ($q) => $q->where('name', 'odontoiatra'))->get();
        $igienisti = User::whereHas('roles', fn ($q) => $q->where('name', 'igienista'))->get();
        $aso = User::whereHas('roles', fn ($q) => $q->where('name', 'aso'))->get();

        // 1 each already comes from DatabaseSeeder's own base roster.
        expect($odontoiatri)->toHaveCount(5)
            ->and($igienisti)->toHaveCount(5)
            ->and($aso)->toHaveCount(5);

        foreach (['giulia.ferrari', 'marco.esposito', 'chiara.ricci', 'luca.gallo'] as $localPart) {
            $user = User::where('email', "{$localPart}@{$emailDomain}")->firstOrFail();
            expect($user->getRoleNames()->all())->toBe(['odontoiatra']);
        }

        foreach (['sara.conti', 'davide.moretti', 'elena.fontana', 'matteo.barbieri'] as $localPart) {
            $user = User::where('email', "{$localPart}@{$emailDomain}")->firstOrFail();
            expect($user->getRoleNames()->all())->toBe(['igienista']);
        }

        foreach (['francesca.bruno', 'alessandro.greco', 'martina.villa', 'simone.ferri'] as $localPart) {
            $user = User::where('email', "{$localPart}@{$emailDomain}")->firstOrFail();
            expect($user->getRoleNames()->all())->toBe(['aso']);
        }

        $resolver->release();
    }
});

test('running the demo team seeder twice does not create duplicates', function () {
    cleanupDemoStudioFiles();
    (new DatabaseSeeder)->run();

    $resolver = app(TenantConnectionResolver::class);
    $tenant = Tenant::where('email', 'info@rossi.test')->firstOrFail();
    $resolver->forTenant($tenant);

    $countAfterFirstRun = User::count();

    (new DemoTeamSeeder)->run('rossi.test');

    expect(User::count())->toBe($countAfterFirstRun);

    $resolver->release();
});

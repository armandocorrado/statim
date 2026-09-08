<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoTeamSeeder;
use Spatie\Permission\PermissionRegistrar;

test('the demo team seeder adds 4 odontoiatri and 4 igienisti per demo tenant', function () {
    (new DatabaseSeeder)->run();

    foreach (['rossi.test', 'bianchi.test'] as $emailDomain) {
        $tenant = \App\Core\Tenancy\Models\Tenant::where('email', "info@{$emailDomain}")->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $odontoiatri = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'odontoiatra'))
            ->get();
        $igienisti = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'igienista'))
            ->get();

        // 1 each already comes from DatabaseSeeder's own base roster.
        expect($odontoiatri)->toHaveCount(5)
            ->and($igienisti)->toHaveCount(5);

        foreach (['giulia.ferrari', 'marco.esposito', 'chiara.ricci', 'luca.gallo'] as $localPart) {
            $user = User::where('email', "{$localPart}@{$emailDomain}")->firstOrFail();
            expect($user->getRoleNames()->all())->toBe(['odontoiatra']);
        }

        foreach (['sara.conti', 'davide.moretti', 'elena.fontana', 'matteo.barbieri'] as $localPart) {
            $user = User::where('email', "{$localPart}@{$emailDomain}")->firstOrFail();
            expect($user->getRoleNames()->all())->toBe(['igienista']);
        }
    }
});

test('running the demo team seeder twice does not create duplicates', function () {
    (new DatabaseSeeder)->run();
    $countAfterFirstRun = User::count();

    (new DemoTeamSeeder)->run();

    expect(User::count())->toBe($countAfterFirstRun);
});

test('the demo team seeder does nothing when the demo tenants do not exist', function () {
    expect(fn () => (new DemoTeamSeeder)->run())->not->toThrow(Exception::class);
    expect(User::count())->toBe(0);
});

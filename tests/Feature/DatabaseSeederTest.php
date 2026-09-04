<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\PermissionRegistrar;

test('the demo seeder provisions both tenants with one user per fixed role', function () {
    (new DatabaseSeeder)->run();

    expect(Tenant::count())->toBe(2);

    foreach (['rossi.test', 'bianchi.test'] as $emailDomain) {
        $tenant = Tenant::where('email', "info@{$emailDomain}")->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach (['admin', 'odontoiatra', 'igienista', 'aso', 'segreteria'] as $role) {
            $user = User::where('email', "{$role}@{$emailDomain}")->firstOrFail();

            expect($user->tenant_id)->toBe($tenant->id)
                ->and($user->getRoleNames()->all())->toBe([$role]);
        }

        expect(Patient::where('tenant_id', $tenant->id)->count())->toBe(5);
    }
});

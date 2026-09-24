<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAppointmentSeeder;

test('the demo appointment seeder gives every odontoiatra 5 appointments per demo studio', function () {
    cleanupDemoStudioFiles();
    (new DatabaseSeeder)->run();

    $resolver = app(TenantConnectionResolver::class);

    foreach (['rossi.test', 'bianchi.test'] as $emailDomain) {
        $tenant = Tenant::where('email', "info@{$emailDomain}")->firstOrFail();
        $resolver->forTenant($tenant);

        $odontoiatri = User::role('odontoiatra')->get();
        expect($odontoiatri)->toHaveCount(5);

        $pairings = [
            'odontoiatra' => 'aso',
            'giulia.ferrari' => 'francesca.bruno',
            'marco.esposito' => 'alessandro.greco',
            'chiara.ricci' => 'martina.villa',
            'luca.gallo' => 'simone.ferri',
        ];

        foreach ($odontoiatri as $operator) {
            // Solo gli appuntamenti odierni/futuri di DemoAppointmentSeeder:
            // DemoHistoricalDataSeeder (che gira dopo, nella stessa
            // DatabaseSeeder::run()) ne aggiunge molti altri nel passato,
            // senza assistant_id — fuori dallo scope di questo test.
            $appointments = Appointment::where('operator_id', $operator->id)
                ->where('start_at', '>=', now()->startOfDay())
                ->get();
            expect($appointments)->toHaveCount(5);

            $localPart = explode('@', $operator->email)[0];
            $expectedAsoEmail = "{$pairings[$localPart]}@{$emailDomain}";
            $expectedAso = User::where('email', $expectedAsoEmail)->firstOrFail();

            foreach ($appointments as $appointment) {
                expect($appointment->assistant_id)->toBe($expectedAso->id);
            }
        }

        // Igienisti e aso restano senza appuntamenti propri in questa passata.
        $igienisti = User::role('igienista')->get();
        foreach ($igienisti as $operator) {
            expect(Appointment::where('operator_id', $operator->id)->count())->toBe(0);
        }

        $resolver->release();
    }
});

test('running the demo appointment seeder twice does not duplicate appointments', function () {
    cleanupDemoStudioFiles();
    (new DatabaseSeeder)->run();

    $resolver = app(TenantConnectionResolver::class);
    $tenant = Tenant::where('email', 'info@rossi.test')->firstOrFail();
    $resolver->forTenant($tenant);

    $countAfterFirstRun = Appointment::count();

    (new DemoAppointmentSeeder)->run();

    expect(Appointment::count())->toBe($countAfterFirstRun);

    $resolver->release();
});

test('the demo appointment seeder does nothing on a studio with no admin/odontoiatra/patients yet', function () {
    $path = sys_get_temp_dir().'/medcare_test_'.bin2hex(random_bytes(8)).'.sqlite';
    touch($path);

    $tenant = Tenant::factory()->create(['database_name' => $path]);

    $resolver = app(TenantConnectionResolver::class);
    $resolver->forTenant($tenant);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    \App\Core\Users\Support\TenantRoleProvisioner::provisionDefaults();

    expect(fn () => (new DemoAppointmentSeeder)->run())->not->toThrow(Exception::class);
    expect(Appointment::count())->toBe(0);

    $resolver->release();
    unlink($path);
});

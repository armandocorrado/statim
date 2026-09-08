<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAppointmentSeeder;
use Spatie\Permission\PermissionRegistrar;

test('the demo appointment seeder gives every odontoiatra 5 appointments per demo tenant', function () {
    (new DatabaseSeeder)->run();

    foreach (['rossi.test', 'bianchi.test'] as $emailDomain) {
        $tenant = Tenant::where('email', "info@{$emailDomain}")->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $odontoiatri = User::where('tenant_id', $tenant->id)->role('odontoiatra')->get();
        expect($odontoiatri)->toHaveCount(5);

        $pairings = [
            'odontoiatra' => 'aso',
            'giulia.ferrari' => 'francesca.bruno',
            'marco.esposito' => 'alessandro.greco',
            'chiara.ricci' => 'martina.villa',
            'luca.gallo' => 'simone.ferri',
        ];

        foreach ($odontoiatri as $operator) {
            $appointments = Appointment::where('operator_id', $operator->id)->get();
            expect($appointments)->toHaveCount(5);

            $localPart = explode('@', $operator->email)[0];
            $expectedAsoEmail = "{$pairings[$localPart]}@{$emailDomain}";
            $expectedAso = User::where('email', $expectedAsoEmail)->firstOrFail();

            foreach ($appointments as $appointment) {
                expect($appointment->assistant_id)->toBe($expectedAso->id);
            }
        }

        // Igienisti e aso restano senza appuntamenti propri in questa passata.
        $igienisti = User::where('tenant_id', $tenant->id)->role('igienista')->get();
        foreach ($igienisti as $operator) {
            expect(Appointment::where('operator_id', $operator->id)->count())->toBe(0);
        }
    }
});

test('running the demo appointment seeder twice does not duplicate appointments', function () {
    (new DatabaseSeeder)->run();
    $countAfterFirstRun = Appointment::count();

    (new DemoAppointmentSeeder)->run();

    expect(Appointment::count())->toBe($countAfterFirstRun);
});

test('the demo appointment seeder does nothing when the demo tenants do not exist', function () {
    expect(fn () => (new DemoAppointmentSeeder)->run())->not->toThrow(Exception::class);
    expect(Appointment::count())->toBe(0);
});

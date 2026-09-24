<?php

namespace Database\Seeders;

use App\Core\Agenda\Support\AppointmentTypeProvisioner;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantUser;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Core\Tenancy\Support\TenantDatabaseCreator;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;
use App\Modules\Dental\Support\DentalServiceCatalogProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Password for every seeded demo user.
     */
    private const DEMO_PASSWORD = 'MedCare#Wild2026';

    /**
     * Seeds two demo studios, each with its OWN real database — non un
     * unico DB condiviso distinto solo da tenant_id (quel modello non
     * esiste più): ogni studio nasce con schema di dominio migrato, RBAC
     * provisionato e utenti veri, esattamente come farà il vero
     * tenant:create (Tappa 4) — qui in forma sufficiente per l'ambiente
     * demo. I tre seeder secondari girano DENTRO la connessione già
     * risolta su quello studio, uno studio alla volta.
     */
    public function run(): void
    {
        $this->createStudio('Studio Dentistico Rossi', 'studio-rossi', 'rossi.test');
        $this->createStudio('Studio Dentistico Bianchi', 'studio-bianchi', 'bianchi.test');
    }

    private function createStudio(string $name, string $slug, string $emailDomain): void
    {
        $databaseName = TenantDatabaseCreator::nameFor($slug);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'database_name' => $databaseName, 'email' => "info@{$emailDomain}", 'is_active' => true],
        );

        TenantDatabaseCreator::ensureExists($databaseName);

        $resolver = app(TenantConnectionResolver::class);
        $resolver->forTenant($tenant);

        Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true]);

        TenantRoleProvisioner::provisionDefaults();
        AppointmentTypeProvisioner::provisionDefaults();
        DentalServiceCatalogProvisioner::provisionDefaults();

        $demoUsers = [
            'admin' => 'Admin',
            'odontoiatra' => 'Odontoiatra',
            'igienista' => 'Igienista',
            'aso' => 'Aso',
            'segreteria' => 'Segreteria',
        ];

        $admin = null;

        foreach ($demoUsers as $role => $displayName) {
            $email = "{$role}@{$emailDomain}";

            $user = User::factory()->create([
                'name' => $displayName,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]);
            $user->assignRole($role);

            TenantUser::query()->updateOrCreate(
                ['email' => $email, 'tenant_id' => $tenant->id],
                ['remote_user_id' => $user->id, 'is_active' => true],
            );

            if ($role === 'admin') {
                $admin = $user;
            }
        }

        Patient::factory(5)->create([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->call(DemoTeamSeeder::class, parameters: ['emailDomain' => $emailDomain]);
        $this->call(DemoAppointmentSeeder::class);
        $this->call(DemoHistoricalDataSeeder::class);

        $resolver->release();
    }
}

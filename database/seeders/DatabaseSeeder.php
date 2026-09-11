<?php

namespace Database\Seeders;

use App\Core\Agenda\Support\AppointmentTypeProvisioner;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;
use App\Modules\Dental\Support\DentalServiceCatalogProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Password for every seeded demo user.
     */
    private const DEMO_PASSWORD = 'medcare!wild';

    /**
     * Seeds two demo tenants so tenant isolation is visible/testable manually:
     * logging in as Studio Rossi must never show Studio Bianchi's patients.
     */
    public function run(): void
    {
        $this->createTenant('Studio Dentistico Rossi', 'studio-rossi', 'rossi.test');
        $this->createTenant('Studio Dentistico Bianchi', 'studio-bianchi', 'bianchi.test');

        $this->call(DemoTeamSeeder::class);
        $this->call(DemoAppointmentSeeder::class);
    }

    private function createTenant(string $name, string $slug, string $emailDomain): void
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'email' => "info@{$emailDomain}",
            'is_active' => true,
        ]);

        TenantRoleProvisioner::provisionDefaults($tenant);
        AppointmentTypeProvisioner::provisionDefaults($tenant);
        DentalServiceCatalogProvisioner::provisionDefaults($tenant);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $demoUsers = [
            'admin' => 'Admin',
            'odontoiatra' => 'Odontoiatra',
            'igienista' => 'Igienista',
            'aso' => 'Aso',
            'segreteria' => 'Segreteria',
        ];

        $admin = null;

        foreach ($demoUsers as $role => $displayName) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $displayName,
                'email' => "{$role}@{$emailDomain}",
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]);
            $user->assignRole($role);

            if ($role === 'admin') {
                $admin = $user;
            }
        }

        Patient::factory(5)->create([
            'tenant_id' => $tenant->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }
}

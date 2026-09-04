<?php

namespace Database\Seeders;

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;
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

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => "admin@{$emailDomain}",
            'password' => Hash::make(self::DEMO_PASSWORD),
        ]);
        $admin->assignRole('admin');

        $collaboratore = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Collaboratore',
            'email' => "collaboratore@{$emailDomain}",
            'password' => Hash::make(self::DEMO_PASSWORD),
        ]);
        $collaboratore->assignRole('collaboratore');

        Patient::factory(5)->create([
            'tenant_id' => $tenant->id,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }
}

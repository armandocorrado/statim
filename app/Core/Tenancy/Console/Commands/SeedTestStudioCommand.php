<?php

namespace App\Core\Tenancy\Console\Commands;

use App\Core\Agenda\Support\AppointmentTypeProvisioner;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantUser;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Models\User;
use App\Modules\Dental\Support\DentalServiceCatalogProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisioning throwaway per testare il login a due step (Tappa 2): crea un
 * database studio REALE con lo schema di dominio completo e un admin vero,
 * e registra la riga corrispondente in tenant_users. NON e' il vero
 * tenant:create (quello arriva con la Tappa 4, con gestione errori/rollback
 * a regola d'arte) - qui l'obiettivo e' solo avere qualcosa di vero contro
 * cui autenticarsi.
 */
class SeedTestStudioCommand extends Command
{
    protected $signature = 'tenant:seed-test-studio
        {slug : Slug dello studio, es. rossi-2}
        {name : Nome dello studio}
        {email : Email del primo utente admin}
        {--password=MedCare#Wild2026 : Password dell\'admin}';

    protected $description = 'Crea uno studio di prova reale (DB + schema + admin) per testare il login a due step';

    public function handle(TenantConnectionResolver $resolver): int
    {
        $slug = $this->argument('slug');
        $databaseName = 'medcare_tenant_'.str_replace('-', '_', $slug);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $this->argument('name'), 'database_name' => $databaseName, 'is_active' => true],
        );

        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");

        $resolver->forTenant($tenant);

        $this->components->task('Migrazione schema di dominio', fn () => Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations',
            '--force' => true,
        ]) === 0);

        TenantRoleProvisioner::provisionDefaults($tenant);
        AppointmentTypeProvisioner::provisionDefaults($tenant);
        DentalServiceCatalogProvisioner::provisionDefaults($tenant);

        $email = $this->argument('email');
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin di prova',
                'password' => Hash::make($this->option('password')),
                'is_active' => true,
            ],
        );

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $user->syncRoles(['admin']);

        TenantUser::query()->updateOrCreate(
            ['email' => $email, 'tenant_id' => $tenant->id],
            ['remote_user_id' => $user->id, 'is_active' => true],
        );

        $resolver->release();

        $this->newLine();
        $this->components->twoColumnDetail('Studio', "{$tenant->name} ({$tenant->slug})");
        $this->components->twoColumnDetail('Database', $tenant->database_name);
        $this->components->twoColumnDetail('Admin', "{$email} / {$this->option('password')}");

        return self::SUCCESS;
    }
}

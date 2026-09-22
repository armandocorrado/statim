<?php

namespace App\Core\Tenancy\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantUser;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Passo transitorio (non il cutover vero, quello resta una tappa a parte):
 * registra un UNICO tenant centrale "legacy" che punta al database condiviso
 * attuale, e vi mappa tutti gli utenti gia' esistenti in quel DB. Cosi' il
 * nuovo login a due step continua a farli accedere esattamente come prima
 * (il vecchio meccanismo tenant_id dentro il DB condiviso resta intatto e
 * li isola comunque una volta connessi), mentre i NUOVI studi reali
 * (tenant:seed-test-studio, poi tenant:create) hanno ciascuno il proprio
 * database e possono davvero comparire distinti nella schermata di scelta.
 */
class BackfillLegacyTenantUsersCommand extends Command
{
    protected $signature = 'tenant:backfill-legacy-users {--slug=legacy-shared}';

    protected $description = 'Registra un tenant "legacy" per il DB condiviso attuale e mappa i suoi utenti in tenant_users';

    public function handle(): int
    {
        $slug = $this->option('slug');
        $databaseName = config('database.connections.'.config('database.default').'.database');

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => 'Studio (architettura precedente)', 'database_name' => $databaseName, 'is_active' => true],
        );

        $count = 0;

        User::query()->orderBy('id')->chunkById(200, function ($users) use ($tenant, &$count) {
            foreach ($users as $user) {
                TenantUser::query()->updateOrCreate(
                    ['email' => $user->email, 'tenant_id' => $tenant->id],
                    ['remote_user_id' => $user->id, 'is_active' => $user->is_active],
                );
                $count++;
            }
        });

        $this->components->info("Tenant legacy [{$slug}] -> {$databaseName}. Mappati {$count} utenti.");

        return self::SUCCESS;
    }
}

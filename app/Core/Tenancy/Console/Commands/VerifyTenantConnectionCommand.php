<?php

namespace App\Core\Tenancy\Console\Commands;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Dimostra la Tappa 1: due studi di prova, due database MySQL fisici
 * separati, la stessa connessione 'tenant' che legge/scrive nell'uno o
 * nell'altro a seconda di quale Tenant e' stato risolto.
 *
 * Comando usa-e-getta per questa tappa: il vero provisioning di uno studio
 * (con CREATE DATABASE + migration + dati iniziali) arriva con tenant:create
 * in una tappa successiva. Qui la tabella "connection_probe" e' creata al
 * volo, non tracciata da nessuna migration.
 */
class VerifyTenantConnectionCommand extends Command
{
    protected $signature = 'tenant:verify-connection';

    protected $description = 'Dimostra che TenantConnectionResolver isola davvero due DB studio separati';

    public function handle(TenantConnectionResolver $resolver): int
    {
        $tenantA = $this->ensureTestTenant('demo-a', 'medcare_tenant_demo_a');
        $tenantB = $this->ensureTestTenant('demo-b', 'medcare_tenant_demo_b');

        foreach ([$tenantA, $tenantB] as $tenant) {
            $this->createDatabaseIfMissing($tenant->database_name);
        }

        $markerA = 'A-'.Str::random(8);
        $markerB = 'B-'.Str::random(8);

        $this->writeMarker($resolver, $tenantA, $markerA);
        $this->writeMarker($resolver, $tenantB, $markerB);

        $seenFromA = $this->readMarkers($resolver, $tenantA);
        $seenFromB = $this->readMarkers($resolver, $tenantB);

        $resolver->release();

        $this->newLine();
        $this->components->twoColumnDetail('Studio A', "{$tenantA->slug} -> DB {$tenantA->database_name}");
        $this->components->twoColumnDetail('  scritto', $markerA);
        $this->components->twoColumnDetail('  riletto da A', implode(', ', $seenFromA));
        $this->newLine();
        $this->components->twoColumnDetail('Studio B', "{$tenantB->slug} -> DB {$tenantB->database_name}");
        $this->components->twoColumnDetail('  scritto', $markerB);
        $this->components->twoColumnDetail('  riletto da B', implode(', ', $seenFromB));
        $this->newLine();

        $isolated = $seenFromA === [$markerA] && $seenFromB === [$markerB];

        if ($isolated) {
            $this->components->info('Isolamento confermato: ogni studio vede solo il proprio marcatore.');

            return self::SUCCESS;
        }

        $this->components->error('Isolamento FALLITO: un marcatore e\' visibile dal DB sbagliato.');

        return self::FAILURE;
    }

    private function ensureTestTenant(string $slug, string $databaseName): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => "Tenant di verifica [{$slug}]",
                'database_name' => $databaseName,
                'is_active' => true,
            ],
        );
    }

    private function createDatabaseIfMissing(string $databaseName): void
    {
        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");
    }

    private function writeMarker(TenantConnectionResolver $resolver, Tenant $tenant, string $marker): void
    {
        $resolver->forTenant($tenant);

        if (! Schema::connection('tenant')->hasTable('connection_probe')) {
            Schema::connection('tenant')->create('connection_probe', function ($table) {
                $table->id();
                $table->string('marker');
                $table->timestamps();
            });
        }

        // Svuotata a ogni esecuzione: il comando resta ripetibile senza
        // accumulare marcatori di run precedenti (tabella usa-e-getta,
        // niente a che vedere con lo schema di dominio).
        DB::connection('tenant')->table('connection_probe')->truncate();
        DB::connection('tenant')->table('connection_probe')->insert(['marker' => $marker, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @return list<string>
     */
    private function readMarkers(TenantConnectionResolver $resolver, Tenant $tenant): array
    {
        $resolver->forTenant($tenant);

        return DB::connection('tenant')->table('connection_probe')->pluck('marker')->all();
    }
}

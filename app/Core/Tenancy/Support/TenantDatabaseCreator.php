<?php

namespace App\Core\Tenancy\Support;

use Illuminate\Support\Facades\DB;

/**
 * Crea il database fisico di un nuovo studio — portabile tra motori
 * (produzione: mysql, `CREATE DATABASE`; test: sqlite, un file reale, mai
 * ':memory:' visto che serve simulare "un altro database" in un test che
 * ne usa già uno di default). Stesso principio già in uso nel progetto per
 * le query (mai SQL specifico di un solo motore) — vedi CLAUDE.md.
 */
class TenantDatabaseCreator
{
    /**
     * @return string il valore da salvare in Tenant::database_name
     */
    public static function nameFor(string $slug): string
    {
        $base = 'medcare_tenant_'.str_replace('-', '_', $slug);

        return self::driver() === 'sqlite'
            ? sys_get_temp_dir().'/'.$base.'.sqlite'
            : $base;
    }

    public static function ensureExists(string $databaseName): void
    {
        if (self::driver() === 'sqlite') {
            if ($databaseName !== ':memory:' && ! file_exists($databaseName)) {
                touch($databaseName);
            }

            return;
        }

        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");
    }

    private static function driver(): string
    {
        return DB::connection('central')->getDriverName();
    }
}

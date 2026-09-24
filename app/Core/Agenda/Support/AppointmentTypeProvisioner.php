<?php

namespace App\Core\Agenda\Support;

use App\Core\Agenda\Models\AppointmentType;

/**
 * Tipi di appuntamento di default per un nuovo tenant. A differenza dei
 * ruoli RBAC o delle finalità dei consensi, il "tipo" non guida logica
 * applicativa — è pura categorizzazione/colore per il calendario — quindi
 * ogni studio può rinominare/aggiungere i propri (nessuna UI di gestione
 * ancora in questa passata, solo lo schema pronto).
 */
class AppointmentTypeProvisioner
{
    /**
     * @return array<string, string>
     */
    public static function defaultTypes(): array
    {
        return [
            'Prima visita' => '#2563eb',
            'Controllo' => '#16a34a',
            'Igiene' => '#0d9488',
            'Urgenza' => '#dc2626',
            'Altro' => '#6b7280',
        ];
    }

    /**
     * Chiamare con la connessione 'tenant' gia' risolta sullo studio giusto
     * (TenantConnectionResolver::forTenant()) - qui non serve piu' sapere
     * quale tenant, la connessione lo fa gia'.
     */
    public static function provisionDefaults(): void
    {
        foreach (self::defaultTypes() as $name => $color) {
            AppointmentType::firstOrCreate(
                ['name' => $name],
                ['color' => $color],
            );
        }
    }
}

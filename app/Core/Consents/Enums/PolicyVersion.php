<?php

namespace App\Core\Consents\Enums;

/**
 * Versione dell'informativa privacy accettata al momento del consenso.
 * Enum applicativo fisso (non un'entità per-tenant): l'informativa è
 * gestita centralmente, uguale per tutti gli studi — aggiungere una nuova
 * versione pubblicata = aggiungere un case qui.
 */
enum PolicyVersion: string
{
    case V1 = 'v1';

    public function label(): string
    {
        return match ($this) {
            self::V1 => 'Versione 1',
        };
    }
}

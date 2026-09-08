<?php

namespace App\Core\Agenda\Enums;

/**
 * Enum applicativo fisso: guida logica reale (query "solo confermati",
 * esclusione dal controllo sovrapposizioni), non una semplice etichetta —
 * stessa ragione per cui i ruoli RBAC e le finalità dei consensi sono fissi.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmato',
            self::Confirmed => 'Confermato',
            self::Completed => 'Completato',
            self::Cancelled => 'Annullato',
            self::NoShow => 'Non presentato',
        };
    }

    /**
     * Uno slot annullato o non presentato libera l'orario: non conta ai
     * fini del controllo sovrapposizioni.
     *
     * @return list<self>
     */
    public static function excludedFromOverlapCheck(): array
    {
        return [self::Cancelled, self::NoShow];
    }
}

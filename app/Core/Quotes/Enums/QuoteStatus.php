<?php

namespace App\Core\Quotes\Enums;

/**
 * Ciclo di stati fisso: bozza → emesso → accettato/rifiutato → in corso
 * → completato. Rejected e Completed sono terminali. Le transizioni
 * valide sono in QuoteTransitions, non sparse nei controller.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Issued => 'Emesso',
            self::Accepted => 'Accettato',
            self::Rejected => 'Rifiutato',
            self::InProgress => 'In corso',
            self::Completed => 'Completato',
        };
    }

    /**
     * Il "preventivo accettato" nel senso lato del termine — usato ovunque
     * nel codice serva la stessa domanda: tasso di accettazione
     * (QuoteController/DashboardController), eleggibilità alla
     * generazione di un documento fiscale (QuotePolicy). Un solo posto da
     * aggiornare se in futuro il bucket cambia.
     *
     * @return list<self>
     */
    public static function acceptedStatuses(): array
    {
        return [self::Accepted, self::InProgress, self::Completed];
    }
}

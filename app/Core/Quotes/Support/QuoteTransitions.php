<?php

namespace App\Core\Quotes\Support;

use App\Core\Quotes\Enums\QuoteStatus;

/**
 * Unica fonte di verità per le transizioni di stato valide — un
 * preventivo non può saltare da Draft a Completed, né tornare indietro.
 * Draft -> Issued passa dall'azione dedicata issue() (congela i totali,
 * stesso principio di BillingDocument), non da questa mappa.
 */
class QuoteTransitions
{
    /**
     * @return array<string, list<string>>
     */
    private static function map(): array
    {
        return [
            QuoteStatus::Issued->value => [QuoteStatus::Accepted->value, QuoteStatus::Rejected->value],
            QuoteStatus::Accepted->value => [QuoteStatus::InProgress->value],
            QuoteStatus::InProgress->value => [QuoteStatus::Completed->value],
        ];
    }

    public static function isAllowed(QuoteStatus $from, QuoteStatus $to): bool
    {
        return in_array($to->value, self::map()[$from->value] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public static function allowedNextValues(QuoteStatus $from): array
    {
        return self::map()[$from->value] ?? [];
    }
}

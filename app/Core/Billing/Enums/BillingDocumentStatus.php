<?php

namespace App\Core\Billing\Enums;

/**
 * Solo due stati, deliberatamente. Una volta emesso (numero assegnato), un
 * documento fiscale non si cancella né si modifica — l'unico modo per
 * correggerlo è una nota di credito (documento separato, futuro). Uno
 * stato "annullato" che facesse sparire un numero già emesso violerebbe il
 * vincolo di numerazione senza buchi.
 */
enum BillingDocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Issued => 'Emesso',
        };
    }
}

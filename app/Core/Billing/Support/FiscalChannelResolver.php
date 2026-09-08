<?php

namespace App\Core\Billing\Support;

use App\Core\Billing\Enums\FiscalChannel;
use App\Core\Billing\Models\BillingDocument;

/**
 * Decide UNA VOLTA SOLA, all'emissione, quale canale fiscale usare — mai
 * entrambi. Regola di dominio, non un dettaglio tecnico: un documento le
 * cui spese sono riportate al Sistema TS non può essere inviato anche
 * allo SdI verso il privato (tutela della riservatezza dei dati
 * sanitari, art. di legge soggetto a proroga annuale — vedi CLAUDE.md).
 *
 * Oggi il destinatario di un documento è sempre un Patient (persona
 * fisica) — non esiste ancora un intestatario azienda (deliberatamente
 * rimandato, vedi CLAUDE.md) — quindi la regola ha un solo ramo. Quando
 * esisterà un destinatario azienda, questo è l'unico punto da estendere:
 * il resto del modulo non deve cambiare.
 */
class FiscalChannelResolver
{
    public static function resolve(BillingDocument $document): FiscalChannel
    {
        if (self::isPrivateIndividual($document)) {
            return FiscalChannel::SistemaTs;
        }

        return FiscalChannel::Sdi;
    }

    private static function isPrivateIndividual(BillingDocument $document): bool
    {
        // Sempre vero oggi: il destinatario è sempre un Patient. Punto di
        // estensione per quando esisterà un intestatario azienda.
        return true;
    }
}

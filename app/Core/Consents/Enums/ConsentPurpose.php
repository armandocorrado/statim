<?php

namespace App\Core\Consents\Enums;

/**
 * Finalità del consenso — enum applicativo fisso, mai testo libero: ogni
 * finalità è destinata a essere letta da un gate/modulo specifico altrove
 * (es. "marketing" governerà il futuro invio massivo del CRM), quindi non
 * ha senso come catalogo modificabile a runtime da un tenant. Aggiungere
 * una finalità futura (es. consultazione FSE) = aggiungere un case qui,
 * nessuna modifica allo schema o al model Consent.
 */
enum ConsentPurpose: string
{
    case Cura = 'cura';
    case Marketing = 'marketing';
    case RefertiOnline = 'referti_online';

    public function label(): string
    {
        return match ($this) {
            self::Cura => 'Cura',
            self::Marketing => 'Marketing',
            self::RefertiOnline => 'Referti e documenti online',
        };
    }
}

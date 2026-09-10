<?php

namespace App\Modules\Dental\Enums;

/**
 * Sezione della cartella clinica — è qui che si applica il permesso
 * parziale dell'igienista (`clinical_records.hygiene.*`): può vedere/
 * scrivere solo le voci di diario/documenti taggate `Hygiene`.
 * L'anamnesi e gli alert (allergie/rischi) NON sono sezionati — sono dati
 * di sicurezza che riguardano chiunque tratti il paziente, non solo chi
 * fa igiene.
 */
enum DentalRecordSection: string
{
    case General = 'general';
    case Hygiene = 'hygiene';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Generale',
            self::Hygiene => 'Igiene',
        };
    }
}

<?php

namespace App\Modules\Dental\Enums;

/**
 * Set iniziale di stati clinici per elemento dentale, estendibile con
 * nuovi case quando serve (mai un catalogo editabile da UI — stesso
 * principio di AppointmentStatus/ConsentPurpose). "Sano" non è un case:
 * l'assenza di qualunque DentalToothCondition per un dente è lo stato di
 * default, per non dover scrivere una riga per ogni dente sano di ogni
 * paziente.
 */
enum ToothCondition: string
{
    case Carious = 'carious';
    case Filled = 'filled';
    case Missing = 'missing';
    case ToExtract = 'to_extract';
    case Implant = 'implant';
    case Crown = 'crown';
    case RootCanalTreated = 'root_canal_treated';
    case Fractured = 'fractured';
    case Bridge = 'bridge';
    case Sealant = 'sealant';

    public function label(): string
    {
        return match ($this) {
            self::Carious => 'Cariato',
            self::Filled => 'Otturato',
            self::Missing => 'Mancante',
            self::ToExtract => 'Da estrarre',
            self::Implant => 'Impianto',
            self::Crown => 'Corona',
            self::RootCanalTreated => 'Devitalizzato',
            self::Fractured => 'Fratturato',
            self::Bridge => 'Ponte',
            self::Sealant => 'Sigillato',
        };
    }
}

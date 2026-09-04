<?php

namespace App\Core\Patients\Enums;

/**
 * Fonte di provenienza del paziente — valore strutturato, non testo libero,
 * per poter alimentare in futuro KPI di acquisizione.
 */
enum PatientSource: string
{
    case Passaparola = 'passaparola';
    case CampagnaSocial = 'campagna_social';
    case Google = 'google';
    case Sito = 'sito';
    case InvioMedico = 'invio_medico';
    case Altro = 'altro';

    public function label(): string
    {
        return match ($this) {
            self::Passaparola => 'Passaparola',
            self::CampagnaSocial => 'Campagna social',
            self::Google => 'Google',
            self::Sito => 'Sito web',
            self::InvioMedico => 'Invio da medico',
            self::Altro => 'Altro',
        };
    }
}

<?php

namespace App\Core\Consents\Enums;

/**
 * Come è stato raccolto il consenso. Solo `Cartaceo` è usato nell'MVP
 * (l'operatore registra un modulo firmato su carta) — Tablet/Portale
 * arriveranno come nuovi case quando quei canali di raccolta esisteranno
 * davvero, non li si crea vuoti in anticipo.
 */
enum ConsentCollectionMethod: string
{
    case Cartaceo = 'cartaceo';

    public function label(): string
    {
        return match ($this) {
            self::Cartaceo => 'Modulo cartaceo',
        };
    }
}

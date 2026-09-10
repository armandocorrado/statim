<?php

namespace App\Modules\Dental\Enums;

enum DentalAlertCategory: string
{
    case Allergy = 'allergy';
    case Risk = 'risk';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Allergia',
            self::Risk => 'Fattore di rischio',
        };
    }
}

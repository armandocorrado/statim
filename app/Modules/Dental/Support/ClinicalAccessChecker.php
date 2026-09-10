<?php

namespace App\Modules\Dental\Support;

use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;

/**
 * Logica di accesso condivisa da tutte le Policy del modulo — un unico
 * punto dove è scritta la regola "pieno OR hygiene", invece di ripeterla
 * in quattro Policy leggermente diverse. segreteria/ASO non hanno NESSUNO
 * dei permessi coinvolti nel catalogo RBAC attuale: per costruzione questi
 * metodi ritornano sempre false per loro, senza bisogno di un controllo
 * ad hoc "blocca segreteria/ASO".
 */
class ClinicalAccessChecker
{
    /**
     * Per risorse non sezionate (anamnesi, alert): qualunque permesso
     * clinico — pieno o solo igiene — basta.
     */
    public static function canView(User $user): bool
    {
        return $user->can('clinical_records.view') || $user->can('clinical_records.hygiene.view');
    }

    public static function canManage(User $user): bool
    {
        return $user->can('clinical_records.update') || $user->can('clinical_records.hygiene.update');
    }

    /**
     * Per risorse sezionate (diario, documenti): l'accesso pieno vede
     * tutto; l'accesso solo-igiene vede solo la sezione Hygiene.
     */
    public static function canViewSection(User $user, DentalRecordSection $section): bool
    {
        if ($user->can('clinical_records.view')) {
            return true;
        }

        return $section === DentalRecordSection::Hygiene && $user->can('clinical_records.hygiene.view');
    }

    public static function canManageSection(User $user, DentalRecordSection $section): bool
    {
        if ($user->can('clinical_records.update')) {
            return true;
        }

        return $section === DentalRecordSection::Hygiene && $user->can('clinical_records.hygiene.update');
    }
}

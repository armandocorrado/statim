<?php

namespace App\Modules\Dental\Support;

use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;

/**
 * Logica di accesso condivisa dalle Policy del piano di cura — stesso
 * ruolo di ClinicalAccessChecker per la cartella clinica, ma qui l'asse
 * non è "pieno OR igiene su una sezione", è "pieno OR igiene solo sulle
 * voci di listino di categoria Hygiene". segreteria/ASO non hanno nessuno
 * dei permessi coinvolti: bloccati per costruzione, nessun controllo ad
 * hoc necessario.
 */
class TreatmentPlanAccessChecker
{
    public static function canManageAny(User $user): bool
    {
        return $user->can('treatment_plans.clinical.manage') || $user->can('treatment_plans.hygiene.manage');
    }

    /**
     * $category è il campo (testo libero, non interpretato da Core)
     * ServiceCatalogItem::category della voce che si vuole aggiungere.
     */
    public static function canManageItem(User $user, ?string $category): bool
    {
        if ($user->can('treatment_plans.clinical.manage')) {
            return true;
        }

        return $category === DentalRecordSection::Hygiene->value && $user->can('treatment_plans.hygiene.manage');
    }
}

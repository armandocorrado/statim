<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Support\ClinicalAccessChecker;

class DentalDiaryEntryPolicy
{
    public function view(User $user, DentalDiaryEntry $entry): bool
    {
        return ClinicalAccessChecker::canViewSection($user, $entry->section);
    }

    /**
     * $section è la sezione con cui si vuole creare la nuova voce —
     * stesso pattern di AppointmentPolicy::create($user, $operatorId).
     */
    public function createFor(User $user, Patient $patient, DentalRecordSection $section): bool
    {
        return ClinicalAccessChecker::canManageSection($user, $section);
    }
}

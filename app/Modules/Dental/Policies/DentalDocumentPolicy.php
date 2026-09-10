<?php

namespace App\Modules\Dental\Policies;

use App\Core\Patients\Models\Patient;
use App\Models\User;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Support\ClinicalAccessChecker;

class DentalDocumentPolicy
{
    public function view(User $user, DentalDocument $document): bool
    {
        return $document->tenant_id === $user->tenant_id
            && ClinicalAccessChecker::canViewSection($user, $document->section);
    }

    public function createFor(User $user, Patient $patient, DentalRecordSection $section): bool
    {
        return $patient->tenant_id === $user->tenant_id
            && ClinicalAccessChecker::canManageSection($user, $section);
    }
}

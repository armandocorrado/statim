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
        return ClinicalAccessChecker::canViewSection($user, $document->section);
    }

    public function createFor(User $user, Patient $patient, DentalRecordSection $section): bool
    {
        return ClinicalAccessChecker::canManageSection($user, $section);
    }
}

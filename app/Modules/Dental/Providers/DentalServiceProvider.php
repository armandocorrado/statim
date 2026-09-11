<?php

namespace App\Modules\Dental\Providers;

use App\Core\Patients\Models\Patient;
use App\Core\Users\Support\TenantRoleProvisioner;
use App\Modules\Dental\Models\DentalAlert;
use App\Modules\Dental\Models\DentalAnamnesis;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Models\DentalToothCondition;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Policies\DentalAlertPolicy;
use App\Modules\Dental\Policies\DentalAnamnesisPolicy;
use App\Modules\Dental\Policies\DentalDiaryEntryPolicy;
use App\Modules\Dental\Policies\DentalDocumentPolicy;
use App\Modules\Dental\Policies\DentalToothConditionPolicy;
use App\Modules\Dental\Policies\DentalTreatmentPlanPolicy;
use App\Modules\Dental\Support\ClinicalAccessChecker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Grafts the dental vertical's clinical roles and permissions onto the core
 * role catalog, without App\Core\Users\Support\TenantRoleProvisioner ever
 * importing anything from this module — see TenantRoleProvisioner::extend().
 *
 * Also registers this module's own policies — Gate::policy() calls for
 * Dental models live HERE, never in the core AppServiceProvider, for the
 * same boundary reason.
 */
class DentalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        TenantRoleProvisioner::extend('dental', fn () => [
            // Admin studio already has full access to everything, including
            // clinical data — grafted here rather than hardcoded in Core so
            // Core stays ignorant of what "clinical data" even means.
            'admin' => [
                'clinical_records.view', 'clinical_records.update',
                'odontogram.view', 'odontogram.update',
                'treatment_plans.clinical.manage',
            ],
            'odontoiatra' => [
                'patients.view',
                'agenda.view.own', 'agenda.manage.own',
                'reports.production.own',
                'treatment_plans.view', 'treatment_plans.clinical.manage',
                'clinical_records.view', 'clinical_records.update',
                'odontogram.view', 'odontogram.update',
            ],
            'igienista' => [
                'patients.view',
                'agenda.view.own', 'agenda.manage.own',
                'reports.production.own',
                'clinical_records.hygiene.view', 'clinical_records.hygiene.update',
                // Vede il piano di cura per intero (treatment_plans.view)
                // ma può gestirne solo le voci di categoria Hygiene — vedi
                // TreatmentPlanAccessChecker::canManageItem(). Nessun
                // accesso a treatment_plans.administer: l'igienista non
                // genera/emette preventivi, resta amministrativo.
                'treatment_plans.view', 'treatment_plans.hygiene.manage',
            ],
        ]);

        Gate::policy(DentalAnamnesis::class, DentalAnamnesisPolicy::class);
        Gate::policy(DentalAlert::class, DentalAlertPolicy::class);
        Gate::policy(DentalDiaryEntry::class, DentalDiaryEntryPolicy::class);
        Gate::policy(DentalDocument::class, DentalDocumentPolicy::class);
        Gate::policy(DentalToothCondition::class, DentalToothConditionPolicy::class);
        Gate::policy(DentalTreatmentPlan::class, DentalTreatmentPlanPolicy::class);

        // Apertura della scheda clinica nel suo insieme — non è legata al
        // ciclo di vita di un singolo model (l'anamnesi potrebbe non
        // esistere ancora), quindi un Gate dedicato invece di un metodo su
        // una Policy specifica.
        Gate::define('view-clinical-record', fn ($user, Patient $patient) => $patient->tenant_id === $user->tenant_id
            && ClinicalAccessChecker::canView($user));
    }
}

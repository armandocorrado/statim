<?php

namespace App\Modules\Dental\Providers;

use App\Core\Users\Support\TenantRoleProvisioner;
use Illuminate\Support\ServiceProvider;

/**
 * Grafts the dental vertical's clinical roles and permissions onto the core
 * role catalog, without App\Core\Users\Support\TenantRoleProvisioner ever
 * importing anything from this module — see TenantRoleProvisioner::extend().
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
            ],
        ]);
    }
}

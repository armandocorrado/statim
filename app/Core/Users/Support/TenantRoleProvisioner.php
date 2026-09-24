<?php

namespace App\Core\Users\Support;

use App\Core\Users\Models\Permission;
use App\Core\Users\Models\Role;

/**
 * Provisions the FIXED role/permission catalog for a tenant.
 *
 * Roles and permissions both live in the tenant's own database now (no more
 * spatie "teams"/tenant_id scoping — each studio has its own physical DB,
 * so a plain Role/Permission catalog is already correctly isolated). Roles
 * are fixed by design (GDPR data minimization / protection by design): the
 * studio Admin assigns these predefined roles to users, but nothing in the
 * app lets a tenant edit a role's permissions — this class is the single
 * source of truth for the catalog.
 *
 * Clinical roles (odontoiatra, igienista) and clinical-data permissions
 * belong to the dental vertical, not here — they're grafted on via extend(),
 * called from App\Modules\Dental\Providers\DentalServiceProvider. This class
 * must never import anything from App\Modules\Dental.
 */
class TenantRoleProvisioner
{
    /** @var array<string, callable(): array<string, list<string>>> */
    private static array $contributors = [];

    /**
     * Registers a role/permission contribution from a vertical module.
     * Keyed so repeated boots (e.g. one per test case re-bootstrapping the
     * app) overwrite rather than accumulate — extend() stays idempotent.
     *
     * @param  callable(): array<string, list<string>>  $contributor
     */
    public static function extend(string $key, callable $contributor): void
    {
        self::$contributors[$key] = $contributor;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function defaultRolePermissions(): array
    {
        $roles = [
            'admin' => [
                'patients.view', 'patients.create', 'patients.update', 'patients.delete',
                'users.view', 'users.invite', 'users.update', 'users.deactivate',
                'agenda.view.all', 'agenda.manage.all',
                'billing.view', 'billing.manage', 'payments.manage',
                'consents.manage', 'crm.manage',
                'treatment_plans.view', 'treatment_plans.administer',
                'reports.production.all',
                'dashboard.admin.view',
            ],
            'aso' => [
                'patients.view',
                'agenda.view.all',
            ],
            'segreteria' => [
                'patients.view', 'patients.create', 'patients.update',
                'agenda.view.all', 'agenda.manage.all',
                'billing.view', 'billing.manage', 'payments.manage',
                'crm.manage', 'consents.manage',
                'treatment_plans.view', 'treatment_plans.administer',
            ],
        ];

        foreach (self::$contributors as $contributor) {
            foreach ($contributor() as $role => $permissions) {
                $roles[$role] = array_values(array_unique([...($roles[$role] ?? []), ...$permissions]));
            }
        }

        return $roles;
    }

    /**
     * Chiamare con la connessione 'tenant' gia' risolta sullo studio giusto
     * (TenantConnectionResolver::forTenant()) - il catalogo finisce nel DB
     * di quello studio per il solo fatto di essere la connessione attiva.
     */
    public static function provisionDefaults(): void
    {
        foreach (array_merge(...array_values(self::defaultRolePermissions())) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::defaultRolePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}

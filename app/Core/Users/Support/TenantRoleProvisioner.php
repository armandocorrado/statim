<?php

namespace App\Core\Users\Support;

use App\Core\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the default role/permission catalog for a tenant.
 *
 * Permissions are global (spatie/laravel-permission does not team-scope the
 * `permissions` table), roles are per-tenant (the "teams" feature scopes
 * `roles` by tenant_id) so each studio can, in the future, customize its own
 * role-to-permission mapping without affecting other tenants.
 */
class TenantRoleProvisioner
{
    /**
     * @return array<string, list<string>>
     */
    public static function defaultRolePermissions(): array
    {
        return [
            'admin' => [
                'patients.view', 'patients.create', 'patients.update', 'patients.delete',
                'users.view', 'users.invite', 'users.update', 'users.deactivate',
            ],
            'utente' => ['patients.view', 'patients.create', 'patients.update'],
        ];
    }

    public static function provisionDefaults(Tenant $tenant): void
    {
        foreach (array_merge(...array_values(self::defaultRolePermissions())) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->id);

        try {
            foreach (self::defaultRolePermissions() as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions);
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}

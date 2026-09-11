<?php

use App\Core\Users\Support\TenantRoleProvisioner;

test('the dental vertical grafts its clinical roles onto the core catalog', function () {
    $roles = TenantRoleProvisioner::defaultRolePermissions();

    expect($roles)->toHaveKeys(['admin', 'aso', 'segreteria', 'odontoiatra', 'igienista']);
});

test('a non-clinical role never receives a clinical data permission', function () {
    // Vincolo invalicabile: un ruolo non clinico non può mai ricevere accesso
    // ai dati clinici. Admin studio is deliberately exempt — it has full
    // access to everything in its studio, clinical data included.
    $clinicalRoles = ['admin', 'odontoiatra', 'igienista'];
    $clinicalPermissionPattern = '/^(clinical_records\.|odontogram\.|treatment_plans\.clinical\.|treatment_plans\.hygiene\.)/';

    foreach (TenantRoleProvisioner::defaultRolePermissions() as $role => $permissions) {
        if (in_array($role, $clinicalRoles, true)) {
            continue;
        }

        $clinicalPermissions = array_filter(
            $permissions,
            fn (string $permission) => preg_match($clinicalPermissionPattern, $permission) === 1,
        );

        expect($clinicalPermissions)
            ->toBe([], "Il ruolo non clinico '{$role}' non deve mai avere permessi clinici, trovati: ".implode(', ', $clinicalPermissions));
    }
});

test('only admin can manage other users', function () {
    // Governance: solo l'Admin studio assegna i ruoli — nessun altro ruolo
    // deve mai poter invitare, aggiornare o disattivare utenti.
    $userManagementPermissionPattern = '/^users\./';

    foreach (TenantRoleProvisioner::defaultRolePermissions() as $role => $permissions) {
        if ($role === 'admin') {
            continue;
        }

        $userManagementPermissions = array_filter(
            $permissions,
            fn (string $permission) => preg_match($userManagementPermissionPattern, $permission) === 1,
        );

        expect($userManagementPermissions)
            ->toBe([], "Il ruolo '{$role}' non deve mai poter gestire altri utenti, trovati: ".implode(', ', $userManagementPermissions));
    }
});

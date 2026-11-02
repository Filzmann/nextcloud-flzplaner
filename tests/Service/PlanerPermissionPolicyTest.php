<?php

declare(strict_types=1);

use OCA\FlzPlaner\Service\PlanerPermissionPolicy;

require_once __DIR__ . '/../bootstrap.php';

$policy = new PlanerPermissionPolicy();
$teamGroup = 'ASN-TeamA';
$ebGroup = 'EB';

$assistantPermissions = $policy->forTeam(['ASN-TeamA'], $teamGroup, $ebGroup);
foreach ([
    PlanerPermissionPolicy::TEAM_READ,
    PlanerPermissionPolicy::ASSIGNMENT_MANAGE_OWN,
    PlanerPermissionPolicy::ASSIGNMENT_PREFERENCE_MANAGE_OWN,
    PlanerPermissionPolicy::WORKLOAD_MANAGE_OWN,
    PlanerPermissionPolicy::FIXED_SHIFT_MANAGE_OWN,
] as $permission) {
    if (!in_array($permission, $assistantPermissions, true)) {
        throw new RuntimeException("Teammitglieder brauchen die eigene Berechtigung {$permission}.");
    }
}
foreach (PlanerPermissionPolicy::TEAM_EB_PERMISSIONS as $permission) {
    if (in_array($permission, $assistantPermissions, true)) {
        throw new RuntimeException("Die Team-Berechtigung {$permission} darf nicht aus reiner Teammitgliedschaft folgen.");
    }
}

$ebPermissions = $policy->forTeam(['ASN-TeamA', 'EB'], $teamGroup, $ebGroup);
foreach (PlanerPermissionPolicy::TEAM_EB_PERMISSIONS as $permission) {
    if (!in_array($permission, $ebPermissions, true)) {
        throw new RuntimeException("Die zuständige Einsatzbegleitung braucht die Einzelberechtigung {$permission}.");
    }
}

if ($policy->forTeam(['ASN-TeamB', 'EB'], $teamGroup, $ebGroup) !== []) {
    throw new RuntimeException('Eine Einsatzbegleitung eines anderen Teams darf keine Team-Berechtigung erhalten.');
}

if (!$policy->mayManageTemporaryAdminGrants(
    ['Datenschutzbeauftragte'],
    'Datenschutzbeauftragte',
    $ebGroup,
    ['ASN-TeamA', 'ASN-TeamB'],
)) {
    throw new RuntimeException('Datenschutzbeauftragte müssen temporäre Adminfreigaben verwalten dürfen.');
}
if (!$policy->mayManageTemporaryAdminGrants(
    ['EB', 'ASN-TeamA'],
    'Datenschutzbeauftragte',
    $ebGroup,
    ['ASN-TeamA', 'ASN-TeamB'],
)) {
    throw new RuntimeException('Eine für ein Team zuständige Einsatzbegleitung muss temporäre Adminfreigaben verwalten dürfen.');
}
foreach ([
    ['EB'],
    ['ASN-TeamA'],
    ['EB', 'ASN-Manipuliert'],
] as $groups) {
    if ($policy->mayManageTemporaryAdminGrants($groups, 'Datenschutzbeauftragte', $ebGroup, ['ASN-TeamA', 'ASN-TeamB'])) {
        throw new RuntimeException('Unvollständige oder manipulierte Rollenkombinationen dürfen keine Adminfreigabe verwalten.');
    }
}

echo "FlzPlaner permission policy tests passed\n";

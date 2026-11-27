<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

/**
 * Zweck: Trennt fachliche Planer-Berechtigungen von den dafür ausgewerteten Organisationsgruppen.
 * Vertrag: Teamrechte entstehen nur aus der aktuellen Team-/EB-Kombination; unbekannte Rechte bleiben verweigert.
 */
final class PlanerPermissionPolicy {
    public const TEAM_READ = 'plan.team.read';
    public const ASSIGNMENT_MANAGE_OWN = 'plan.assignment.manage-own';
    public const ASSIGNMENT_PREFERENCE_MANAGE_OWN = 'plan.assignment.preference.manage-own';
    public const WORKLOAD_MANAGE_OWN = 'plan.workload.manage-own';
    public const FIXED_SHIFT_MANAGE_OWN = 'plan.fixed-shift.manage-own';
    public const ASSIGNMENT_MANAGE_TEAM = 'plan.assignment.manage-team';
    public const TEAM_SETTINGS_MANAGE = 'plan.team-settings.manage';
    public const STATUS_TRANSITION = 'plan.status.transition';
    public const WORKLOAD_READ_TEAM = 'plan.workload.read-team';
    public const FIXED_CONFLICT_RESOLVE = 'plan.fixed-conflict.resolve';

    public const TEAM_EB_PERMISSIONS = [
        self::ASSIGNMENT_MANAGE_TEAM,
        self::TEAM_SETTINGS_MANAGE,
        self::STATUS_TRANSITION,
        self::WORKLOAD_READ_TEAM,
        self::FIXED_CONFLICT_RESOLVE,
    ];

    public const TEAM_MEMBER_PERMISSIONS = [
        self::TEAM_READ,
        self::ASSIGNMENT_MANAGE_OWN,
        self::ASSIGNMENT_PREFERENCE_MANAGE_OWN,
        self::WORKLOAD_MANAGE_OWN,
        self::FIXED_SHIFT_MANAGE_OWN,
    ];

    /** @param list<string> $actorGroups @return list<string> */
    public function forTeam(array $actorGroups, string $teamGroup, string $ebGroup): array {
        $groups = array_fill_keys(array_map('strval', $actorGroups), true);
        if ($teamGroup === '' || !isset($groups[$teamGroup])) {
            return [];
        }

        $permissions = self::TEAM_MEMBER_PERMISSIONS;
        if ($ebGroup !== '' && isset($groups[$ebGroup])) {
            $permissions = [...$permissions, ...self::TEAM_EB_PERMISSIONS];
        }
        return $permissions;
    }

    /** @param list<string> $actorGroups */
    public function mayManageTemporaryAdminGrants(
        array $actorGroups,
        string $privacyGroup,
        string $ebGroup,
        array $teamGroups,
    ): bool {
        $groups = array_values(array_unique(array_map('strval', $actorGroups)));
        if ($privacyGroup !== '' && in_array($privacyGroup, $groups, true)) {
            return true;
        }
        if ($ebGroup === '' || !in_array($ebGroup, $groups, true)) {
            return false;
        }
        $validTeams = array_fill_keys(array_map('strval', $teamGroups), true);
        foreach ($groups as $groupId) {
            if (isset($validTeams[$groupId])) {
                return true;
            }
        }
        return false;
    }
}

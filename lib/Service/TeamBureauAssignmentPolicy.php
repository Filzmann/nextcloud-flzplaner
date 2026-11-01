<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

/** Reine Policy für die EB-eigene, team- und bürogebundene Zuordnung. */
final class TeamBureauAssignmentPolicy {
    /** @param list<string> $actorGroups @param list<string> $areaGroups */
    public function mayAssign(array $actorGroups, string $teamGroup, string $ebGroup, array $areaGroups, string $targetAreaGroup): bool {
        if (!in_array($ebGroup, $actorGroups, true) || !in_array($teamGroup, $actorGroups, true)) {
            return false;
        }
        $assignedAreas = array_values(array_unique(array_intersect($areaGroups, $actorGroups)));
        return count($assignedAreas) === 1 && $assignedAreas[0] === $targetAreaGroup;
    }
}

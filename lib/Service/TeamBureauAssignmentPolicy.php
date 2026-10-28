<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

/** Reine Policy für EB- und bereichsgebundene Leitungszuordnungen. */
final class TeamBureauAssignmentPolicy {
    /** @param list<string> $actorGroups @param list<string> $leadershipGroups */
    public function mayAssign(array $actorGroups, string $teamGroup, string $ebGroup, array $leadershipGroups, string $targetAreaGroup, ?string $currentAreaGroup): bool {
        $inTarget = in_array($targetAreaGroup, $actorGroups, true);
        if (!$inTarget) return false;
        $isResponsibleEb = in_array($ebGroup, $actorGroups, true) && in_array($teamGroup, $actorGroups, true);
        if ($isResponsibleEb) return true;
        $isLeadership = array_intersect($leadershipGroups, $actorGroups) !== [];
        if (!$isLeadership) return false;
        return $currentAreaGroup === null || in_array($currentAreaGroup, $actorGroups, true);
    }
}

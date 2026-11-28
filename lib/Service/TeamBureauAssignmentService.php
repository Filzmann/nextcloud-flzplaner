<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;


use OCP\IGroupManager;
use OCP\IUserSession;

/** Autorisiert und persistiert die kanonische Einzelbüro-Zuordnung eines ASN-Teams. */
final class TeamBureauAssignmentService {
    public function __construct(
        private IGroupManager $groups,
        private IUserSession $session,
        private PlanerOrganizationGateway $organization,
        private TeamSettingsService $settings,
        private TeamBureauAssignmentPolicy $policy,
    ) {}

    public function assign(string $teamCode, string $bureauKey): array {
        $user = $this->session->getUser();
        if ($user === null) throw new \DomainException('Nicht angemeldet.');
        $definition = $this->organization->definitionOrDefaults();
        $teamCode = $definition->normalizeTeamCode($teamCode);
        $targetArea = $definition->areaGroupId($bureauKey);
        if ($targetArea === null) throw new \InvalidArgumentException('Der Bürobereich ist unbekannt.');
        $actorGroups = array_map('strval', $this->groups->getUserGroupIds($user));
        $allowed = $this->policy->mayAssign(
            $actorGroups,
            $definition->teamGroupPrefix() . $teamCode,
            $definition->roleGroupId('eb'),
            $definition->areaGroupIds(),
            $targetArea,
        );
        if (!$allowed) throw new \DomainException('Keine Berechtigung für diese Bürozuordnung.');
        return $this->settings->saveBureauKey($teamCode, $bureauKey);
    }
}

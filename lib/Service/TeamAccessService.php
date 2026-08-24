<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\Assistant;
use OCA\AdPlaner\Model\Team;
use OCA\LocalBase\Organization\AdOrganizationDefinition;
use OCA\LocalBase\Organization\AdOrganizationSettingsService;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Zweck: Leitet sichtbare Assistenzteams und EB-Koordinationsrechte aus aktuellen Nextcloud-Gruppen ab.
 * Zusammenspiel: API/ScheduleService -> TeamAccessService -> gemeinsame Organisationsdefinition und TeamSettingsService.
 * Vertrag: Deny by default; Teammitgliedschaft und EB-Rolle müssen für Koordinationsrechte gleichzeitig vorliegen.
 */
class TeamAccessService {
    public function __construct(
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private TeamSettingsService $settingsService,
        private ?AdOrganizationSettingsService $organization = null,
    ) {
    }

    public function currentUserId(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Nicht angemeldet.');
        }

        return $user->getUID();
    }

    public function teamsForCurrentUser(): array {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return [];
        }

        $groupIds = $this->groupManager->getUserGroupIds($user);
        $teamCodes = [];
        $prefix = $this->definition()->teamGroupPrefix();
        foreach ($groupIds as $groupId) {
            $groupId = (string)$groupId;
            if (!str_starts_with($groupId, $prefix) || $groupId === $prefix) continue;
            $code = substr($groupId, strlen($prefix));
            try {
                $teamCodes[] = $this->normalizeTeamCode($code);
            } catch (\InvalidArgumentException) {
            }
        }

        $teamCodes = array_values(array_unique($teamCodes));
        usort($teamCodes, 'strcasecmp');

        return array_values(array_filter(array_map(
            fn(string $teamCode): ?Team => $this->teamForCode($teamCode),
            $teamCodes
        )));
    }

    private function teamForCode(string $teamCode): ?Team {
        $teamCode = $this->normalizeTeamCode($teamCode);
        $groupName = $this->teamGroupName($teamCode);
        $group = $this->groupManager->get($groupName);
        if ($group === null) {
            return null;
        }

        $settings = $this->settingsService->settingsForTeam($teamCode);
        $assistants = $this->assistantsForGroup($group);

        return new Team(
            $teamCode,
            $groupName,
            $settings->displayName,
            $assistants,
            $this->currentUserIsEbForTeam($teamCode),
            $settings->config
        );
    }

    public function assertTeamAccess(string $teamCode): Team {
        $teamCode = $this->normalizeTeamCode($teamCode);
        if (!$this->currentUserInGroup($this->teamGroupName($teamCode))) {
            throw new \DomainException('Kein Zugriff auf dieses Assistenzteam.');
        }

        $team = $this->teamForCode($teamCode);
        if ($team === null) {
            throw new \DomainException('Kein Zugriff auf dieses Assistenzteam.');
        }

        return $team;
    }

    public function assertCanCoordinate(string $teamCode): Team {
        $team = $this->assertTeamAccess($teamCode);
        if (!$team->isEb) {
            throw new \DomainException('Nur die Einsatzbegleitung darf diese Aktion ausführen.');
        }

        return $team;
    }

    public function assertAssistantInTeam(string $teamCode, string $assistantUid): void {
        $team = $this->assertTeamAccess($teamCode);
        if ($team->assistantByUid($assistantUid) !== null) {
            return;
        }

        throw new \DomainException('Diese Assistenz gehört nicht zum Team.');
    }

    public function normalizeTeamCode(string $teamCode): string {
        return $this->definition()->normalizeTeamCode($teamCode);
    }

    public function currentUserIsEbForTeam(string $teamCode): bool {
        $teamCode = $this->normalizeTeamCode($teamCode);

        return $this->currentUserInGroup($this->teamGroupName($teamCode)) && $this->currentUserHasEbGroup();
    }

    public function assistantLabelMap(array $assistants): array {
        $map = [];
        foreach ($assistants as $assistant) {
            $assistant = $assistant instanceof Assistant ? $assistant : Assistant::get((array)$assistant);
            $map[$assistant->uid] = $assistant->displayName;
        }

        return $map;
    }

    private function assistantsForGroup($group): array {
        $assistants = [];
        foreach ($group->getUsers() as $user) {
            if (!$user->isEnabled()) {
                continue;
            }

            $isEb = $this->userHasEbGroup($user);
            $assistants[] = Assistant::fromUser($user, $isEb);
        }

        usort($assistants, static fn(Assistant $a, Assistant $b): int => strcasecmp($a->displayName, $b->displayName));

        return $assistants;
    }

    private function currentUserInGroup(string $groupName): bool {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        return in_array($groupName, $this->groupManager->getUserGroupIds($user), true);
    }

    private function currentUserHasEbGroup(): bool {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        return $this->userHasEbGroup($user);
    }

    private function userHasEbGroup($user): bool {
        if ($user === null) {
            return false;
        }

        return in_array($this->definition()->roleGroupId('eb'), array_map('strval', $this->groupManager->getUserGroupIds($user)), true);
    }

    private function teamGroupName(string $teamCode): string {
        return $this->definition()->teamGroupPrefix() . $teamCode;
    }

    public function organizationContract(): array {
        $definition = $this->definition();

        return [
            'teamGroupPrefix' => $definition->teamGroupPrefix(),
            'teamLabelPrefix' => $definition->teamLabelPrefix(),
            'teamCodeMaxLength' => $definition->teamCodeMaxLength(),
            'coordinatorGroupId' => $definition->roleGroupId('eb'),
            'coordinatorLabel' => $definition->roleLabel('eb'),
        ];
    }

    private function definition(): AdOrganizationDefinition {
        return $this->organization?->definition() ?? AdOrganizationDefinition::defaults();
    }
}

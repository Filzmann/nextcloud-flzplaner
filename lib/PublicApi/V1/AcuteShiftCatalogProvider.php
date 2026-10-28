<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\PublicApi\V1;

use OCA\FlzPlaner\Service\TeamSettingsService;
use OCA\LocalBase\Organization\FlzOrganizationSettingsService;
use OCP\IGroupManager;

/** Öffentlicher read-only V1-Katalog; unzugeordnete Teams werden fail-closed nicht ausgeliefert. */
final class AcuteShiftCatalogProvider {
    public function __construct(
        private IGroupManager $groups,
        private FlzOrganizationSettingsService $organization,
        private TeamSettingsService $settings,
    ) {}

    /** @param list<string> $bureauKeys @return list<AcuteShiftTeam> */
    public function teamsForBureaux(array $bureauKeys): array {
        $bureauKeys = array_fill_keys(array_map('strval', $bureauKeys), true);
        if ($bureauKeys === []) return [];
        $definition = $this->organization->definition();
        $prefix = $definition->teamGroupPrefix();
        $ebGroup = $definition->roleGroupId('eb');
        $result = [];
        foreach ($this->groups->search($prefix, 10000, 0) as $group) {
            $groupId = (string)$group->getGID();
            if (!str_starts_with($groupId, $prefix) || $groupId === $prefix) continue;
            $teamCode = $definition->normalizeTeamCode(substr($groupId, strlen($prefix)));
            $settings = $this->settings->settingsForTeam($teamCode);
            if ($settings->bureauKey === null || !isset($bureauKeys[$settings->bureauKey])) continue;
            $assistants = [];
            foreach ($group->getUsers() as $user) {
                if (!$user->isEnabled() || in_array($ebGroup, $this->groups->getUserGroupIds($user), true)) continue;
                $assistants[] = ['uid' => (string)$user->getUID(), 'displayName' => (string)($user->getDisplayName() ?: $user->getUID())];
            }
            usort($assistants, static fn(array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
            $templates = array_values(array_map(static fn(array $shift): array => [
                'key' => (string)$shift['key'], 'label' => (string)$shift['label'], 'startsAt' => (string)$shift['startsAt'], 'endsAt' => (string)$shift['endsAt'],
            ], array_filter($settings->config['shifts'] ?? [], static fn(array $shift): bool => (bool)($shift['enabled'] ?? false))));
            $result[] = new AcuteShiftTeam($teamCode, $settings->displayName, $settings->bureauKey, $templates, $assistants);
        }
        usort($result, static fn(AcuteShiftTeam $a, AcuteShiftTeam $b): int => strcasecmp($a->displayName(), $b->displayName()));
        return $result;
    }
}

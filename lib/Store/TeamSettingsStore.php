<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Store;

use OCA\AdPlaner\Model\TeamSettings;
use OCA\AdPlaner\Repository\TeamSettingsRepository;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\LocalBase\Organization\AdOrganizationDefinition;
use OCA\LocalBase\Organization\AdOrganizationSettingsService;

class TeamSettingsStore {
    public function __construct(
        private TeamSettingsRepository $repository,
        private ShiftConfigService $shiftConfig,
        private ?AdOrganizationSettingsService $organization = null,
    ) {
    }

    public function forTeam(string $teamCode): TeamSettings {
        $row = $this->repository->findByCode($teamCode);
        if ($row === null) {
            return new TeamSettings($teamCode, $this->defaultDisplayName($teamCode), $this->shiftConfig->defaults());
        }

        $decoded = json_decode((string)($row['settings_json'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $displayName = trim((string)($row['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $this->defaultDisplayName($teamCode);
        }

        return new TeamSettings(
            $teamCode,
            $displayName,
            $this->shiftConfig->normalize($decoded)
        );
    }

    public function save(string $teamCode, string $displayName, array $config): TeamSettings {
        $displayName = trim($displayName);
        if ($displayName === '') {
            throw new \InvalidArgumentException('Der Anzeigename darf nicht leer sein.');
        }
        $displayNameLength = preg_match_all('/./us', $displayName);
        if ($displayNameLength === false) {
            throw new \InvalidArgumentException('Der Anzeigename muss gültiges UTF-8 enthalten.');
        }
        if ($displayNameLength > 255) {
            throw new \InvalidArgumentException('Der Anzeigename darf höchstens 255 Zeichen lang sein.');
        }

        $normalized = $this->shiftConfig->normalize($config);
        $this->repository->save($teamCode, $displayName, $normalized);

        return new TeamSettings($teamCode, $displayName, $normalized);
    }

    private function defaultDisplayName(string $teamCode): string {
        $definition = $this->organization?->definition() ?? AdOrganizationDefinition::defaults();
        return $definition->teamLabelPrefix() . ' ' . $teamCode;
    }
}

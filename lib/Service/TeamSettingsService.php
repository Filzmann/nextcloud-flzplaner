<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

use OCA\FlzPlaner\Model\TeamSettings;
use OCA\FlzPlaner\Store\TeamSettingsStore;

class TeamSettingsService {
    public function __construct(
        private TeamSettingsStore $store
    ) {
    }

    public function settingsForTeam(string $teamCode): TeamSettings {
        return $this->store->forTeam($teamCode);
    }

    public function save(string $teamCode, string $displayName, array $config): array {
        return $this->store->save($teamCode, $displayName, $config)->toArray();
    }
}

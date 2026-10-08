<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Model;

use OCA\LocalBase\Model\ModelApiTrait;

class TeamSettings {
    use ModelApiTrait;

    public function __construct(
        public string $teamCode,
        public string $displayName,
        public array $config
    ) {
    }

    protected static function fromArray(array $data): self {
        return new self(
            (string)($data['teamCode'] ?? $data['team_code'] ?? ''),
            (string)($data['displayName'] ?? $data['display_name'] ?? ''),
            is_array($data['config'] ?? null) ? $data['config'] : []
        );
    }

    public function toArray(): array {
        return [
            'displayName' => $this->displayName,
            'config' => $this->config,
        ];
    }
}

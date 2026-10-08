<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Model;

use OCA\LocalBase\Model\ModelApiTrait;

class ShiftDefinition {
    use ModelApiTrait;

    public function __construct(
        public string $key,
        public string $label,
        public string $startsAt,
        public string $endsAt,
        public bool $enabled = true
    ) {
    }

    protected static function fromArray(array $shift): self {
        return new self(
            (string)($shift['key'] ?? ''),
            (string)($shift['label'] ?? ''),
            (string)($shift['startsAt'] ?? $shift['starts_at'] ?? ''),
            (string)($shift['endsAt'] ?? $shift['ends_at'] ?? ''),
            (bool)($shift['enabled'] ?? true)
        );
    }

    public function toArray(): array {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'enabled' => $this->enabled,
        ];
    }
}

<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Model;

use OCA\LocalBase\Model\ModelApiTrait;

class ShiftSlot {
    use ModelApiTrait;

    public function __construct(
        public int $id,
        public string $teamCode,
        public string $planMonth,
        public string $workDate,
        public string $segmentKey,
        public string $label,
        public string $startsAt,
        public string $endsAt,
        public bool $enabled,
        public array $candidates = []
    ) {
    }

    protected static function fromArray(array $data): self {
        return new self(
            (int)($data['id'] ?? 0),
            (string)($data['teamCode'] ?? $data['team_code'] ?? ''),
            (string)($data['planMonth'] ?? $data['plan_month'] ?? ''),
            (string)($data['workDate'] ?? $data['work_date'] ?? ''),
            (string)($data['segmentKey'] ?? $data['segment_key'] ?? ''),
            (string)($data['label'] ?? ''),
            (string)($data['startsAt'] ?? $data['starts_at'] ?? ''),
            (string)($data['endsAt'] ?? $data['ends_at'] ?? ''),
            (bool)($data['enabled'] ?? true),
            ShiftCandidate::get_all(is_array($data['candidates'] ?? null) ? $data['candidates'] : [])
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'teamCode' => $this->teamCode,
            'planMonth' => $this->planMonth,
            'workDate' => $this->workDate,
            'segmentKey' => $this->segmentKey,
            'label' => $this->label,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'enabled' => $this->enabled,
            'candidates' => array_map(
                static fn($candidate): array => $candidate instanceof ShiftCandidate ? $candidate->toArray() : (array)$candidate,
                $this->candidates
            ),
        ];
    }
}

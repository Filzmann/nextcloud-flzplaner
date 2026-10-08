<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Model;

use OCA\LocalBase\Model\ModelApiTrait;

class DayNote {
    use ModelApiTrait;

    public function __construct(
        public string $teamCode,
        public string $workDate,
        public string $note,
        public string $updatedByUid = '',
        public string $updatedAt = ''
    ) {
    }

    protected static function fromArray(array $data): self {
        return new self(
            (string)($data['teamCode'] ?? $data['team_code'] ?? ''),
            (string)($data['workDate'] ?? $data['work_date'] ?? ''),
            (string)($data['note'] ?? ''),
            (string)($data['updatedByUid'] ?? $data['updated_by_uid'] ?? ''),
            (string)($data['updatedAt'] ?? $data['updated_at'] ?? '')
        );
    }

    public function toArray(): array {
        return [
            'teamCode' => $this->teamCode,
            'workDate' => $this->workDate,
            'note' => $this->note,
        ];
    }
}

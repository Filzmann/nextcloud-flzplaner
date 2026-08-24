<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

use OCA\LocalBase\Model\ModelApiTrait;

class ShiftCandidate {
    use ModelApiTrait;

    public function __construct(
        public int $id,
        public int $slotId,
        public string $assistantUid,
        public string $createdByUid,
        public string $createdAt = '',
        public string $displayName = '',
        public bool $isSelf = false
    ) {
    }

    protected static function fromArray(array $data): self {
        return new self(
            (int)($data['id'] ?? 0),
            (int)($data['slotId'] ?? $data['slot_id'] ?? 0),
            (string)($data['assistantUid'] ?? $data['assistant_uid'] ?? $data['uid'] ?? ''),
            (string)($data['createdByUid'] ?? $data['created_by_uid'] ?? ''),
            (string)($data['createdAt'] ?? $data['created_at'] ?? ''),
            (string)($data['displayName'] ?? $data['display_name'] ?? ''),
            (bool)($data['isSelf'] ?? $data['is_self'] ?? false)
        );
    }

    public function toArray(array $assistantLabels = [], string $currentUid = ''): array {
        return [
            'id' => $this->id,
            'uid' => $this->assistantUid,
            'displayName' => $assistantLabels[$this->assistantUid] ?? ($this->displayName !== '' ? $this->displayName : $this->assistantUid),
            'isSelf' => $this->isSelf || $this->assistantUid === $currentUid,
        ];
    }
}

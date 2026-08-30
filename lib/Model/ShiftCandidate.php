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
        public bool $isSelf = false,
        public string $preference = 'neutral',
        public string $note = '',
        public string $source = 'manual',
        public bool $fixedDeleted = false,
        public bool $fixedModified = false
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
            (bool)($data['isSelf'] ?? $data['is_self'] ?? false),
            (string)($data['preference'] ?? 'neutral'),
            (string)($data['note'] ?? $data['candidate_note'] ?? ''),
            (string)($data['source'] ?? $data['assignment_source'] ?? 'manual'),
            (bool)($data['fixedDeleted'] ?? $data['fixed_deleted'] ?? false),
            (bool)($data['fixedModified'] ?? $data['fixed_modified'] ?? false)
        );
    }

    public function toArray(array $assistantLabels = [], string $currentUid = ''): array {
        return [
            'id' => $this->id,
            'uid' => $this->assistantUid,
            'displayName' => $assistantLabels[$this->assistantUid] ?? ($this->displayName !== '' ? $this->displayName : $this->assistantUid),
            'isSelf' => $this->isSelf || $this->assistantUid === $currentUid,
            'fixed' => $this->source === 'regular' && !$this->fixedDeleted,
            'preference' => $this->preference,
            'note' => $this->note,
        ];
    }
}

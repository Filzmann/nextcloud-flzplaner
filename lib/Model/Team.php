<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Model;

use OCA\FlzPlaner\Vendor\Filzmann\Shared\Model\ModelApiTrait;
use OCA\FlzPlaner\Service\PlanerPermissionPolicy;

class Team {
    use ModelApiTrait;

    public function __construct(
        public string $code,
        public string $groupName,
        public string $displayName,
        public array $assistants,
        public bool $isEb,
        public array $settings,
        public array $permissions = [],
    ) {
        $this->assistants = $this->normalizeAssistants($this->assistants);
    }

    protected static function fromArray(array $data): self {
        return new self(
            (string)($data['code'] ?? ''),
            (string)($data['groupName'] ?? $data['group_name'] ?? ''),
            (string)($data['displayName'] ?? $data['display_name'] ?? ''),
            is_array($data['assistants'] ?? null) ? $data['assistants'] : [],
            (bool)($data['isEb'] ?? $data['is_eb'] ?? $data['canCoordinate'] ?? false),
            is_array($data['settings'] ?? null) ? $data['settings'] : [],
            is_array($data['permissions'] ?? null) ? array_values(array_map('strval', $data['permissions'])) : []
        );
    }

    public function toArray(): array {
        return [
            'code' => $this->code,
            'groupName' => $this->groupName,
            'displayName' => $this->displayName,
            'assistants' => $this->assistantsArray(),
            'isEb' => $this->isEb,
            'permissions' => $this->permissions,
            'canManageTeamAssignments' => $this->hasPermission(PlanerPermissionPolicy::ASSIGNMENT_MANAGE_TEAM),
            'canManageTeamSettings' => $this->hasPermission(PlanerPermissionPolicy::TEAM_SETTINGS_MANAGE),
            'canTransitionStatus' => $this->hasPermission(PlanerPermissionPolicy::STATUS_TRANSITION),
            'canReadTeamWorkload' => $this->hasPermission(PlanerPermissionPolicy::WORKLOAD_READ_TEAM),
            'canResolveFixedConflicts' => $this->hasPermission(PlanerPermissionPolicy::FIXED_CONFLICT_RESOLVE),
            'settings' => $this->settings,
        ];
    }

    public function hasPermission(string $permission): bool {
        return in_array($permission, $this->permissions, true);
    }

    public function assistants(): array {
        return $this->assistants;
    }

    public function assistantsArray(): array {
        return array_values(array_map(
            static fn(Assistant $assistant): array => $assistant->toArray(),
            array_filter(
                $this->assistants,
                static fn(Assistant $assistant): bool => $assistant->canReceiveShifts
            )
        ));
    }

    public function assistantLabelMap(): array {
        $map = [];
        foreach ($this->assistants as $assistant) {
            $map[$assistant->uid] = $assistant->displayName;
        }

        return $map;
    }

    public function assignableAssistantUidMap(): array {
        $map = [];
        foreach ($this->assistants as $assistant) {
            if (!$assistant->canReceiveShifts) {
                continue;
            }

            $map[$assistant->uid] = true;
        }

        return $map;
    }

    public function assistantByUid(string $uid): ?Assistant {
        foreach ($this->assistants as $assistant) {
            if ($assistant->uid === $uid) {
                return $assistant;
            }
        }

        return null;
    }

    private function normalizeAssistants(array $assistants): array {
        return array_map(static function ($assistant): Assistant {
            if ($assistant instanceof Assistant) {
                return $assistant;
            }

            return Assistant::get((array)$assistant);
        }, $assistants);
    }
}

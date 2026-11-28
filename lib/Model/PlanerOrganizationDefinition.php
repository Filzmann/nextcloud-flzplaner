<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Model;

use InvalidArgumentException;

/** App-local, read-only projection of the public flz_organization definition. */
final class PlanerOrganizationDefinition
{
    private function __construct(private array $data)
    {
    }

    public static function defaults(): self
    {
        return self::fromArray([
            'teamGroupPrefix' => 'ASN-',
            'teamLabelPrefix' => 'Assistenzteam',
            'teamCodeMaxLength' => 16,
            'roles' => [
                'eb' => ['groupId' => 'EB', 'label' => 'Einsatzbegleitung'],
            ],
            'areas' => [
                'northeast' => ['groupId' => 'Bereich-Nordost', 'label' => 'Nordost'],
                'west' => ['groupId' => 'Bereich-West', 'label' => 'West'],
                'south' => ['groupId' => 'Bereich-Sued', 'label' => 'Süd'],
            ],
            'hierarchy' => [],
            'organizationTeams' => [],
        ]);
    }

    public static function fromArray(array $data): self
    {
        foreach (['roles', 'areas', 'hierarchy', 'organizationTeams'] as $key)
        {
            if (!is_array($data[$key] ?? null))
            {
                throw new InvalidArgumentException('Ungültige Organisationsdefinition.');
            }
        }
        if (!is_string($data['teamGroupPrefix'] ?? null)
            || !is_string($data['teamLabelPrefix'] ?? null)
            || !is_int($data['teamCodeMaxLength'] ?? null)
            || $data['teamCodeMaxLength'] < 1)
        {
            throw new InvalidArgumentException('Ungültige Organisationsteam-Definition.');
        }
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function roles(): array
    {
        return $this->data['roles'];
    }

    public function areas(): array
    {
        return $this->data['areas'];
    }

    public function hierarchy(): array
    {
        return $this->data['hierarchy'];
    }

    public function organizationTeams(): array
    {
        return $this->data['organizationTeams'];
    }

    public function teamGroupPrefix(): string
    {
        return $this->data['teamGroupPrefix'];
    }

    public function teamLabelPrefix(): string
    {
        return $this->data['teamLabelPrefix'];
    }

    public function teamCodeMaxLength(): int
    {
        return $this->data['teamCodeMaxLength'];
    }

    public function normalizeTeamCode(string $teamCode): string
    {
        $teamCode = trim($teamCode);
        if (!preg_match('/^[\\p{L}\\p{N}]{1,' . $this->teamCodeMaxLength() . '}$/u', $teamCode))
        {
            throw new InvalidArgumentException('Ungültiger Organisationsteam-Code.');
        }
        return $teamCode;
    }

    /** @return list<string> */
    public function roleGroupIds(): array
    {
        return array_values(array_column($this->roles(), 'groupId'));
    }

    /** @return list<string> */
    public function areaGroupIds(): array
    {
        return array_values(array_column($this->areas(), 'groupId'));
    }

    /** @return list<string> */
    public function roleKeysForGroups(array $groups): array
    {
        $lookup = array_fill_keys(array_map('strval', $groups), true);
        return array_values(array_keys(array_filter(
            $this->roles(),
            static fn(array $role): bool => isset($lookup[$role['groupId'] ?? '']),
        )));
    }

    public function roleGroupId(string $key): ?string
    {
        return $this->roles()[$key]['groupId'] ?? null;
    }

    public function roleLabel(string $key): string
    {
        return $this->roles()[$key]['label'] ?? $key;
    }

    public function areaGroupId(string $key): ?string
    {
        return $this->areas()[$key]['groupId'] ?? null;
    }

    public function roleIsAreaScopedByGroup(string $groupId): bool
    {
        foreach ($this->roles() as $role)
        {
            if (($role['groupId'] ?? null) === $groupId)
            {
                return (bool)($role['areaScoped'] ?? false);
            }
        }
        return false;
    }

    public function roleManagementIsAreaScoped(string $key): bool
    {
        return (bool)($this->roles()[$key]['managementAreaScoped'] ?? false);
    }
}

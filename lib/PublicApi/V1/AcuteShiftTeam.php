<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\PublicApi\V1;

use InvalidArgumentException;

/** Read-only Teamprojektion für Akutschicht-Consumer. */
final class AcuteShiftTeam {
    public const CONTRACT_VERSION = '1.0';

    /** @param list<array{key:string,label:string,startsAt:string,endsAt:string}> $shiftTemplates
     *  @param list<array{uid:string,displayName:string}> $assistants
     */
    public function __construct(
        private string $teamCode,
        private string $displayName,
        private string $bureauKey,
        private array $shiftTemplates,
        private array $assistants,
    ) {
        if (trim($teamCode) === '' || trim($displayName) === '' || trim($bureauKey) === '') throw new InvalidArgumentException('Team, name and bureau are required.');
    }

    public function contractVersion(): string { return self::CONTRACT_VERSION; }
    public function teamCode(): string { return $this->teamCode; }
    public function displayName(): string { return $this->displayName; }
    public function bureauKey(): string { return $this->bureauKey; }
    public function shiftTemplates(): array { return $this->shiftTemplates; }
    public function assistants(): array { return $this->assistants; }
}

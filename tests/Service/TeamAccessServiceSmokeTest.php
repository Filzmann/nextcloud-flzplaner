<?php

declare(strict_types=1);

namespace {
    if (!interface_exists(\OCP\IGroupManager::class)) {
        eval('namespace OCP; interface IGroupManager { public function get($gid); public function getUserGroupIds($user); }');
    }
    if (!interface_exists(\OCP\IUserSession::class)) {
        eval('namespace OCP; interface IUserSession { public function getUser(); }');
    }

    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Model\TeamSettings;
    use OCA\AdPlaner\Service\TeamAccessService;
    use OCA\AdPlaner\Service\TeamSettingsService;
    use OCP\IGroupManager;
    use OCP\IUserSession;
    use function OCA\AdPlaner\Tests\assertDomainException;
    use function OCA\AdPlaner\Tests\assertSameValue;

    $alice = new class('alice', 'Alice Assistenz', 'alice@example.invalid') {
        public function __construct(
            private string $uid,
            private string $displayName,
            private string $email
        ) {
        }

        public function getUID(): string {
            return $this->uid;
        }

        public function getDisplayName(): string {
            return $this->displayName;
        }

        public function getEMailAddress(): string {
            return $this->email;
        }

        public function isEnabled(): bool {
            return true;
        }
    };
    $bob = new class('bob', 'Bob EB', 'bob@example.invalid') {
        public function __construct(
            private string $uid,
            private string $displayName,
            private string $email
        ) {
        }

        public function getUID(): string {
            return $this->uid;
        }

        public function getDisplayName(): string {
            return $this->displayName;
        }

        public function getEMailAddress(): string {
            return $this->email;
        }

        public function isEnabled(): bool {
            return true;
        }
    };
    $disabledAssistant = new class {
        public function getUID(): string { return 'disabled-assistant'; }
        public function getDisplayName(): string { return 'Disabled Assistant'; }
        public function isEnabled(): bool { return false; }
    };
    $legacyEb = new class('legacy-eb', 'Legacy EB', '') {
        public function __construct(private string $uid, private string $displayName, private string $email) {}
        public function getUID(): string { return $this->uid; }
        public function getDisplayName(): string { return $this->displayName; }
        public function getEMailAddress(): string { return $this->email; }
    };
    $zeroNameUser = new class {
        public function getUID(): string { return 'zero-name'; }
        public function getDisplayName(): string { return '0'; }
    };
    assertSameValue(
        '0',
        \OCA\AdPlaner\Model\Assistant::fromUser($zeroNameUser, false)->displayName,
        'The valid Nextcloud display name "0" must not be replaced with the uid.'
    );

    $teamGroup = new class([$alice, $bob, $disabledAssistant]) {
        public int $getUsersCalls = 0;

        public function __construct(private array $users) {
        }

        public function getUsers(): array {
            $this->getUsersCalls++;
            return $this->users;
        }
    };
    $groupManager = new class($teamGroup, $bob) implements IGroupManager {
        public function __construct(
            private object $teamGroup,
            private object $currentUser
        ) {
        }

        public function get($gid): ?object {
            return match ((string)$gid) {
                'ad-ASN-TeamB' => $this->teamGroup,
                default => null,
            };
        }

        public function getUserGroupIds($user): array {
            $uid = $user->getUID();
            if ($uid === 'bob') {
                return ['ad-ASN-TeamB', 'ad-EB'];
            }
            if ($uid === 'alice') {
                return ['ad-ASN-TeamB', 'ad-ASN-Zulu', 'ignored', 'ad-ASN-TeamB'];
            }
            if ($uid === 'legacy-eb') {
                return ['ad-ASN-TeamB', 'ad-EB-Altschema'];
            }

            return [];
        }
    };

    $session = new class($bob) implements IUserSession {
        public function __construct(private ?object $user) {
        }

        public function getUser(): ?object {
            return $this->user;
        }

        public function setUser(?object $user): void {
            $this->user = $user;
        }
    };

    $settings = new class extends TeamSettingsService {
        public int $settingsCalls = 0;

        public function __construct() {
        }

        public function settingsForTeam(string $teamCode): TeamSettings {
            $this->settingsCalls++;
            return new TeamSettings($teamCode, $teamCode === 'TeamB' ? 'Team B' : $teamCode, ['meetingDay' => '2026-07-15']);
        }
    };

    $service = new TeamAccessService($groupManager, $session, $settings);

    assertSameValue('bob', $service->currentUserId(), 'Current user id should come from the user session.');
    assertSameValue('TeamB', $service->normalizeTeamCode(' TeamB '), 'Team codes should be trimmed.');
    assertSameValue(true, $service->currentUserIsEbForTeam('TeamB'), 'EB users should be detected through ad-EB groups.');

    $team = $service->assertTeamAccess('TeamB');
    assertSameValue('Team B', $team->displayName, 'Team display name should come from team settings.');
    assertSameValue('ad-ASN-TeamB', $team->groupName, 'Team group name should follow the AD schema.');
    assertSameValue(['Alice Assistenz', 'Bob EB'], array_map(static fn($assistant): string => $assistant->displayName, $team->assistants()), 'Assistants should be sorted by display name.');
    assertSameValue(null, $team->assistantByUid('disabled-assistant'), 'Disabled Nextcloud users must not be exposed as current shift-capable team members.');
    assertSameValue(false, $team->assistantByUid('bob')->canReceiveShifts, 'EB users should not receive shifts.');
    assertSameValue(
        ['alice'],
        array_column($team->toArray()['assistants'] ?? [], 'uid'),
        'Public team payloads should contain only current shift-capable assistants.'
    );
    assertSameValue(['alice' => 'Alice Assistenz', 'bob' => 'Bob EB'], $service->assistantLabelMap($team->assistants()), 'Assistant label maps should expose display names by uid.');
    assertSameValue(
        ['carla' => 'Carla Assistenz'],
        $service->assistantLabelMap([['uid' => 'carla', 'displayName' => 'Carla Assistenz']]),
        'Assistant label maps should normalize array input.'
    );
    assertSameValue(
        [
            'teamGroupPrefix' => 'ad-ASN-',
            'teamLabelPrefix' => 'Assistenzteam',
            'teamCodeMaxLength' => 16,
            'coordinatorGroupId' => 'ad-EB',
            'coordinatorLabel' => 'Einsatzbegleitung',
        ],
        $service->organizationContract(),
        'The public organization contract should expose the shared defaults.'
    );

    $service->assertCanCoordinate('TeamB');
    $service->assertAssistantInTeam('TeamB', 'alice');
    assertDomainException(
        static fn() => $service->assertAssistantInTeam('TeamB', 'missing'),
        'Assistants outside the selected team should be rejected.'
    );
    assertDomainException(
        static fn() => $service->assertTeamAccess('Missing'),
        'Missing teams should not be accessible.'
    );

    $session->setUser($alice);
    assertSameValue(['TeamB'], array_map(static fn($team): string => $team->code, $service->teamsForCurrentUser()), 'Teams for current user should derive unique ASN group codes and skip missing groups.');
    assertSameValue(false, $service->currentUserIsEbForTeam('TeamB'), 'Non-EB team users should not coordinate.');
    assertDomainException(
        static fn() => $service->assertCanCoordinate('TeamB'),
        'Non-EB users should not coordinate team settings.'
    );

    $session->setUser($legacyEb);
    assertSameValue(false, $service->currentUserIsEbForTeam('TeamB'), 'Legacy ad-EB-* groups must not grant EB rights.');

    $session->setUser(null);
    assertSameValue([], $service->teamsForCurrentUser(), 'Anonymous sessions should not expose teams.');
    assertSameValue(false, $service->currentUserIsEbForTeam('TeamB'), 'Anonymous sessions should not receive EB rights.');
    $settings->settingsCalls = 0;
    $teamGroup->getUsersCalls = 0;
    assertDomainException(
        static fn() => $service->assertTeamAccess('TeamB'),
        'Anonymous sessions should not access an existing team.'
    );
    assertSameValue(0, $settings->settingsCalls, 'Denied team access must not load protected team settings.');
    assertSameValue(0, $teamGroup->getUsersCalls, 'Denied team access must not enumerate protected team members.');
    assertSameValue(true, (new \ReflectionMethod(TeamAccessService::class, 'teamForCode'))->isPrivate(), 'The unguarded team loader must not remain a public service path.');
    try {
        $service->currentUserId();
        throw new RuntimeException('Anonymous sessions received a user id.');
    } catch (RuntimeException $error) {
        assertSameValue('Nicht angemeldet.', $error->getMessage(), 'Anonymous sessions should fail closed.');
    }

    echo 'TeamAccessService smoke tests passed' . PHP_EOL;
}

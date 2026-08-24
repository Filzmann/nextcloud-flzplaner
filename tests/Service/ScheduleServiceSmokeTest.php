<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Service\ScheduleService;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\AdPlaner\Service\TeamAccessService;
use OCA\AdPlaner\Service\PlanningHintService;
use OCA\AdPlaner\Store\ShiftPlanStore;
use function OCA\AdPlaner\Tests\assertDomainException;
use function OCA\AdPlaner\Tests\assertSameValue;

class FakeShiftPlanStoreForSchedule extends ShiftPlanStore {
    public array $added = [];
    public array $removed = [];
    public array $slots = [];
    public array $updatedSlots = [];
    public array $insertedSlots = [];
    public array $savedNotes = [];
    public array $deletedNotes = [];
    public bool $slotEnabled = true;
    public string $status = 'draft';
    public ?string $statusOnNextLock = null;
    public int $transactionCalls = 0;
    public array $lockCalls = [];

    public function __construct() {
    }

    public function monthStatus(string $teamCode, string $month): string {
        return $this->status;
    }

    public function transactional(callable $operation): mixed {
        $this->transactionCalls++;
        return $operation();
    }

    public function ensureMonthStatus(string $teamCode, string $month, string $updatedByUid): void {}

    public function lockMonthStatus(string $teamCode, string $month, array $expectedStatuses): ?string {
        $this->lockCalls[] = compact('teamCode', 'month', 'expectedStatuses');
        if ($this->statusOnNextLock !== null) {
            $this->status = $this->statusOnNextLock;
            $this->statusOnNextLock = null;
        }

        return in_array($this->status, $expectedStatuses, true) ? $this->status : null;
    }

    public function slotForMonth(int $slotId, string $teamCode, string $month): ?ShiftSlot {
        return new ShiftSlot($slotId, $teamCode, $month, $month . '-01', 'early', 'Früh', '08:00', '14:00', $this->slotEnabled);
    }

    public function addCandidate(int $slotId, string $assistantUid, string $createdByUid): void {
        $this->added[] = compact('slotId', 'assistantUid', 'createdByUid');
    }

    public function removeCandidate(int $slotId, string $assistantUid): void {
        $this->removed[] = compact('slotId', 'assistantUid');
    }

    public function saveDayNote(string $teamCode, string $workDate, string $note, string $updatedByUid): void {
        $this->savedNotes[] = compact('teamCode', 'workDate', 'note', 'updatedByUid');
    }

    public function deleteDayNote(string $teamCode, string $workDate): void {
        $this->deletedNotes[] = compact('teamCode', 'workDate');
    }

    public function slotsForMonth(string $teamCode, string $month): array {
        if ($this->slots !== []) {
            return $this->slots;
        }

        return [
            new ShiftSlot(1, $teamCode, $month, $month . '-01', 'early', 'Früh', '08:00', '14:00', true),
        ];
    }

    public function candidatesForSlotIds(array $slotIds): array {
        return [
            1 => [
                new ShiftCandidate(1, 1, 'assistant-a', 'test-eb'),
                new ShiftCandidate(2, 1, 'test-eb', 'test-eb'),
            ],
        ];
    }

    public function dayNotesForMonth(string $teamCode, string $month): array {
        return [];
    }

    public function updateSlotDefinition(int $slotId, string $label, string $startsAt, string $endsAt, bool $enabled): void {
        $this->updatedSlots[] = compact('slotId', 'label', 'startsAt', 'endsAt', 'enabled');

        foreach ($this->slots as $slot) {
            if ($slot->id !== $slotId) {
                continue;
            }

            $slot->label = $label;
            $slot->startsAt = $startsAt;
            $slot->endsAt = $endsAt;
            $slot->enabled = $enabled;
        }
    }

    public function insertSlot(
        string $teamCode,
        string $month,
        string $workDate,
        string $segmentKey,
        string $label,
        string $startsAt,
        string $endsAt,
        bool $enabled
    ): int {
        $id = 99 + count($this->insertedSlots);
        $this->insertedSlots[] = compact(
            'id',
            'teamCode',
            'month',
            'workDate',
            'segmentKey',
            'label',
            'startsAt',
            'endsAt',
            'enabled'
        );

        if ($this->slots !== []) {
            $this->slots[] = new ShiftSlot($id, $teamCode, $month, $workDate, $segmentKey, $label, $startsAt, $endsAt, $enabled);
        }

        return $id;
    }
}

class FakeTeamAccessServiceForSchedule extends TeamAccessService {
    public function __construct() {
    }
}

class FakePlanningHintServiceForSchedule extends PlanningHintService {
    public function __construct() {}
    public function forMonth(string $month, array $employeeUids): array {
        return [$month . '-01' => [[
            'employeeUid' => 'assistant-a',
            'type' => 'absence',
            'marker' => 'U?',
            'label' => 'Urlaub',
            'blocks' => false,
        ]]];
    }
}

$assistants = [
    ['uid' => 'assistant-a', 'displayName' => 'Assistant A', 'isEb' => false, 'canReceiveShifts' => true],
    ['uid' => 'test-eb', 'displayName' => 'Test EB', 'isEb' => true, 'canReceiveShifts' => false],
];

$assistantModel = \OCA\AdPlaner\Model\Assistant::get($assistants[0]);
$assistantModels = \OCA\AdPlaner\Model\Assistant::get_all($assistants);
assertSameValue(true, $assistantModel instanceof \OCA\AdPlaner\Model\Assistant, 'Assistant::get should hydrate API data.');
assertSameValue(2, count($assistantModels), 'Assistant::get_all should hydrate API lists.');

$settings = [
    'shifts' => [
        ['key' => 'early', 'label' => 'Früh', 'startsAt' => '08:00', 'endsAt' => '14:00', 'enabled' => true],
    ],
];

$assistantTeam = new Team('A1', 'ad-ASN-A1', 'Team A1', $assistants, false, $settings);
$ebTeam = new Team('A1', 'ad-ASN-A1', 'Team A1', $assistants, true, $settings);
$mappedTeam = Team::get($ebTeam->toArray());
assertSameValue('Team A1', $mappedTeam->toArray()['displayName'], 'Team::get should keep the API payload shape.');

$store = new FakeShiftPlanStoreForSchedule();
$service = new ScheduleService($store, new ShiftConfigService(), new FakeTeamAccessServiceForSchedule(), new FakePlanningHintServiceForSchedule());

$approvalRaceStore = new FakeShiftPlanStoreForSchedule();
$approvalRaceStore->statusOnNextLock = 'approved';
$approvalRaceService = new ScheduleService($approvalRaceStore, new ShiftConfigService(), new FakeTeamAccessServiceForSchedule(), new FakePlanningHintServiceForSchedule());
$approvalRacePlan = $approvalRaceService->monthPlan($ebTeam, '2026-07', 'test-eb');
assertSameValue('approved', $approvalRacePlan['status'] ?? null, 'A concurrent approval must win over mutable month materialization.');
assertSameValue([], $approvalRaceStore->updatedSlots, 'A concurrent approval must prevent slot-definition rewrites.');
assertSameValue([], $approvalRaceStore->insertedSlots, 'A concurrent approval must prevent missing-slot inserts.');
assertSameValue(1, $approvalRaceStore->transactionCalls, 'Mutable month materialization must run inside one transaction.');

$service->addCandidate($assistantTeam, '2026-07', 9, '', 'assistant-a');
assertSameValue('assistant-a', $store->added[0]['assistantUid'] ?? null, 'Assistant should be able to add themself.');

$service->addCandidate($ebTeam, '2026-07', 9, 'assistant-a', 'test-eb');
assertSameValue('assistant-a', $store->added[1]['assistantUid'] ?? null, 'EB should be able to assign an assistant.');

$service->removeCandidate($assistantTeam, '2026-07', 9, '', 'assistant-a');
assertSameValue(
    ['slotId' => 9, 'assistantUid' => 'assistant-a'],
    $store->removed[0] ?? null,
    'An assistant should be able to remove their own request.'
);
$service->removeCandidate($ebTeam, '2026-07', 9, 'assistant-a', 'test-eb');
assertSameValue(
    ['slotId' => 9, 'assistantUid' => 'assistant-a'],
    $store->removed[1] ?? null,
    'The responsible EB should be able to remove another assistant assignment.'
);

assertDomainException(
    static fn() => $service->addCandidate($ebTeam, '2026-07', 9, '', 'test-eb'),
    'EB should not be able to add themself without selecting an assistant.'
);
assertDomainException(
    static fn() => $service->addCandidate($ebTeam, '2026-07', 9, 'test-eb', 'test-eb'),
    'EB accounts should not be assignable to shifts.'
);
assertDomainException(
    static fn() => $service->addCandidate($assistantTeam, '2026-07', 9, 'test-eb', 'assistant-a'),
    'Assistants should not assign other users.'
);
assertDomainException(
    static fn() => $service->removeCandidate($assistantTeam, '2026-07', 9, 'test-eb', 'assistant-a'),
    'Assistants should not remove other users.'
);
assertDomainException(
    static fn() => $service->saveDayNote($assistantTeam, '2026-07', '2026-07-01', 'Nicht erlaubt', 'assistant-a'),
    'Assistants should not edit day notes.'
);
$addedBeforeDisabledMutation = count($store->added);
$store->slotEnabled = false;
assertDomainException(
    static fn() => $service->addCandidate($ebTeam, '2026-07', 9, 'assistant-a', 'test-eb'),
    'Disabled shift slots must reject direct candidate mutations.'
);
assertSameValue($addedBeforeDisabledMutation, count($store->added), 'A rejected disabled-slot mutation must not persist a candidate.');
$store->slotEnabled = true;

$store->status = 'planned';
$store->statusOnNextLock = 'approved';
$addedBeforeConcurrentApproval = count($store->added);
assertDomainException(
    static fn() => $service->addCandidate($ebTeam, '2026-07', 9, 'assistant-a', 'test-eb'),
    'A candidate mutation racing with approval must fail closed.'
);
assertSameValue($addedBeforeConcurrentApproval, count($store->added), 'Concurrent approval must prevent the candidate write.');

$store->status = 'planned';
$store->statusOnNextLock = 'approved';
assertDomainException(
    static fn() => $service->saveDayNote($ebTeam, '2026-07', '2026-07-01', 'Nicht speichern', 'test-eb'),
    'A note mutation racing with approval must fail closed.'
);
assertSameValue([], $store->savedNotes, 'Concurrent approval must prevent the note write.');
$store->status = 'draft';

$mismatchedMonthMessage = '';
try {
    $service->saveDayNote($ebTeam, '2026-07', '2026-08-01', 'Nicht speichern', 'test-eb');
} catch (\Throwable $error) {
    $mismatchedMonthMessage = $error->getMessage();
}
assertSameValue(
    'Das Datum gehört nicht zum ausgewählten Planungsmonat.',
    $mismatchedMonthMessage,
    'A day note should reject a work date outside the requested plan month.'
);
assertSameValue([], $store->savedNotes, 'A rejected cross-month day note must not be persisted.');
$service->saveDayNote($ebTeam, '2026-07', '2026-07-01', '  Hinweis  ', 'test-eb');
assertSameValue(
    [[
        'teamCode' => 'A1',
        'workDate' => '2026-07-01',
        'note' => 'Hinweis',
        'updatedByUid' => 'test-eb',
    ]],
    $store->savedNotes,
    'A day note inside the requested month should be trimmed and persisted.'
);
$service->saveDayNote($ebTeam, '2026-07', '2026-07-01', " \n\t ", 'test-eb');
assertSameValue(
    [['teamCode' => 'A1', 'workDate' => '2026-07-01']],
    $store->deletedNotes,
    'Clearing a day note should remove its no-longer-needed row and editor metadata.'
);
assertSameValue(1, count($store->savedNotes), 'Clearing a day note must not persist an empty replacement row.');

$maximumNote = str_repeat('Ä', 2000);
$service->saveDayNote($ebTeam, '2026-07', '2026-07-01', $maximumNote, 'test-eb');
assertSameValue($maximumNote, $store->savedNotes[array_key_last($store->savedNotes)]['note'] ?? null, 'A 2,000-character Unicode day note should be valid.');

$assertInvalidNoteWithoutWrite = static function (string $note, string $message) use ($service, $ebTeam, $store): void {
    $before = count($store->savedNotes);
    try {
        $service->saveDayNote($ebTeam, '2026-07', '2026-07-01', $note, 'test-eb');
    } catch (\InvalidArgumentException) {
        assertSameValue($before, count($store->savedNotes), $message);
        return;
    }

    throw new \RuntimeException($message);
};
$assertInvalidNoteWithoutWrite(str_repeat('Ä', 2001), 'A day note longer than 2,000 Unicode characters must be rejected without persistence.');
$assertInvalidNoteWithoutWrite("\xC3\x28", 'An invalid UTF-8 day note must be rejected without persistence.');

$plan = $service->monthPlan($ebTeam, '2026-07', 'test-eb');
$slotCandidates = $plan['days'][0]['slots'][0]['candidates'] ?? [];
assertSameValue(['assistant-a'], array_column($slotCandidates, 'uid'), 'Month plan should hide non-assignable EB candidates.');
assertSameValue(false, array_key_exists('createdByUid', $slotCandidates[0] ?? []), 'Month plans must not expose the internal candidate creator uid.');
assertSameValue('Assistant A', $plan['days'][0]['hints'][0]['displayName'] ?? null, 'Planning hints use the visible team label without exposing foreign details.');
assertSameValue(false, array_key_exists('employeeUid', $plan['days'][0]['hints'][0] ?? []), 'Public planning hints must not expose an internal Nextcloud uid once the visible label is resolved.');

$configuredStore = new FakeShiftPlanStoreForSchedule();
$configuredStore->slots = [
    new ShiftSlot(10, 'A1', '2026-07', '2026-07-01', 'early', 'Altfrüh', '07:00', '13:00', true),
    new ShiftSlot(11, 'A1', '2026-07', '2026-07-01', 'obsolete', 'Alt', '00:00', '01:00', true),
];
$configuredTeam = new Team('A1', 'ad-ASN-A1', 'Team A1', $assistants, true, [
    'shifts' => [
        ['key' => 'early', 'label' => 'Früh neu', 'startsAt' => '08:00', 'endsAt' => '14:00', 'enabled' => true],
        ['key' => 'late', 'label' => 'Spät', 'startsAt' => '14:00', 'endsAt' => '20:00', 'enabled' => true],
        ['key' => 'night', 'label' => 'Nacht', 'startsAt' => '20:00', 'endsAt' => '08:00', 'enabled' => false],
    ],
]);
$configuredService = new ScheduleService($configuredStore, new ShiftConfigService(), new FakeTeamAccessServiceForSchedule(), new FakePlanningHintServiceForSchedule());
$configuredPlan = $configuredService->monthPlan($configuredTeam, '2026-07', 'test-eb');
$updatesById = [];
foreach ($configuredStore->updatedSlots as $updatedSlot) {
    $updatesById[$updatedSlot['slotId']] = $updatedSlot;
}

assertSameValue('Früh neu', $updatesById[10]['label'] ?? null, 'Existing slots should be updated to the current shift label.');
assertSameValue(false, $updatesById[11]['enabled'] ?? null, 'Slots for removed shift segments should be disabled.');
assertSameValue('late', $configuredStore->insertedSlots[0]['segmentKey'] ?? null, 'Missing enabled segments should be inserted for the first day.');
assertSameValue(false, in_array('night', array_column($configuredStore->insertedSlots, 'segmentKey'), true), 'Disabled shift segments should not be inserted.');
assertSameValue('Früh neu', $configuredPlan['days'][0]['slots'][0]['label'] ?? null, 'Month plan should use refreshed slot definitions.');

echo 'AdPlaner schedule smoke tests passed' . PHP_EOL;

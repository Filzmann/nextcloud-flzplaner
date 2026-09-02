<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Service\ScheduleService;
use OCA\AdPlaner\Service\WorkloadPreferenceService;
use OCA\AdPlaner\Service\FixedShiftService;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\AdPlaner\Service\TeamAccessService;
use OCA\AdPlaner\Service\PlanningHintService;
use OCA\AdPlaner\Store\ShiftPlanStore;
use function OCA\AdPlaner\Tests\assertDomainException;
use function OCA\AdPlaner\Tests\assertSameValue;

final class MonthPlanStatusStoreFake extends ShiftPlanStore {
    /** @var array<string, string> */
    public array $statuses = [];
    public array $added = [];
    public array $updatedSlots = [];
    public array $insertedSlots = [];
    public int $transactionCalls = 0;
    public bool $rejectNextTransition = false;
    public array $candidatesBySlot = [];
    public array $regularRules = [];

    public function __construct() {}

    public function monthStatus(string $teamCode, string $month): string {
        return $this->statuses[$teamCode . '|' . $month] ?? 'draft';
    }

    public function transitionMonthStatus(string $teamCode, string $month, string $expectedStatus, string $targetStatus, string $updatedByUid): bool {
        if ($this->rejectNextTransition) {
            $this->rejectNextTransition = false;
            return false;
        }
        $key = $teamCode . '|' . $month;
        if ($this->monthStatus($teamCode, $month) !== $expectedStatus) {
            return false;
        }
        $this->statuses[$key] = $targetStatus;
        return true;
    }

    public function ensureMonthStatus(string $teamCode, string $month, string $updatedByUid): void {
        $this->statuses[$teamCode . '|' . $month] ??= 'draft';
    }

    public function lockMonthStatus(string $teamCode, string $month, array $expectedStatuses): ?string {
        $status = $this->monthStatus($teamCode, $month);

        return in_array($status, $expectedStatuses, true) ? $status : null;
    }

    public function transactional(callable $operation): mixed {
        $this->transactionCalls++;
        $statuses = $this->statuses;
        $updatedSlots = $this->updatedSlots;
        $insertedSlots = $this->insertedSlots;
        try {
            return $operation();
        } catch (\Throwable $exception) {
            $this->statuses = $statuses;
            $this->updatedSlots = $updatedSlots;
            $this->insertedSlots = $insertedSlots;
            throw $exception;
        }
    }

    public function slotForMonth(int $slotId, string $teamCode, string $month): ?ShiftSlot {
        return new ShiftSlot($slotId, $teamCode, $month, $month . '-01', 'early', 'Früh', '08:00', '14:00', true);
    }

    public function slotsForMonth(string $teamCode, string $month): array {
        return [new ShiftSlot(1, $teamCode, $month, $month . '-01', 'early', 'Früh', '08:00', '14:00', true)];
    }

    public function candidatesForSlotIds(array $slotIds): array { return $this->candidatesBySlot; }
    public function dayNotesForMonth(string $teamCode, string $month): array { return []; }
    public function workloadLimitsForTeam(string $teamCode): array { return []; }
    public function candidateDates(string $teamCode, string $from, string $to): array { return []; }
    public function regularShiftRulesForTeam(string $teamCode): array { return $this->regularRules; }
    public function materializeFixedCandidate(int $slotId,string $uid): void {}
    public function removeCandidate(int $slotId,string $uid): void { $this->candidatesBySlot[$slotId]=array_values(array_filter($this->candidatesBySlot[$slotId]??[],static fn($candidate):bool=>$candidate->assistantUid!==$uid)); }
    public function fixedConflictReports(array $slotIds): array { return []; }
    public function deletedFixedSlotIds(array $slotIds,string $uid): array { return []; }

    public function addCandidate(int $slotId, string $assistantUid, string $createdByUid): void {
        $this->added[] = compact('slotId', 'assistantUid', 'createdByUid');
    }

    public function updateSlotDefinition(int $slotId, string $label, string $startsAt, string $endsAt, bool $enabled): void {
        $this->updatedSlots[] = compact('slotId', 'label', 'startsAt', 'endsAt', 'enabled');
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
        $this->insertedSlots[] = compact(
            'teamCode',
            'month',
            'workDate',
            'segmentKey',
            'label',
            'startsAt',
            'endsAt',
            'enabled'
        );
        return 100 + count($this->insertedSlots);
    }
}

final class MonthPlanStatusTeamAccessFake extends TeamAccessService {
    public function __construct() {}
}

final class MonthPlanStatusHintServiceFake extends PlanningHintService {
    public function __construct() {}
    public function forMonth(string $month, array $employeeUids): array { return []; }
    public function contextForMonth(string $month, array $employeeUids, array $segments): array { return ['hints'=>[],'unavailable'=>[]]; }
    public function assertAvailableForSlot(ShiftSlot $slot, string $employeeUid): void {}
}

$assistants = [[
    'uid' => 'assistant-a',
    'displayName' => 'Assistant A',
    'isEb' => false,
    'canReceiveShifts' => true,
],[
    'uid' => 'assistant-b',
    'displayName' => 'Assistant B',
    'isEb' => false,
    'canReceiveShifts' => true,
]];
$settings = ['shifts' => [[
    'key' => 'early',
    'label' => 'Früh',
    'startsAt' => '08:00',
    'endsAt' => '14:00',
    'enabled' => true,
]]];
$assistantTeam = new Team('A1', 'ad-ASN-A1', 'Team A1', $assistants, false, $settings);
$ebTeam = new Team('A1', 'ad-ASN-A1', 'Team A1', $assistants, true, $settings);
$store = new MonthPlanStatusStoreFake();
$service = new ScheduleService($store, new ShiftConfigService(), new MonthPlanStatusTeamAccessFake(), new MonthPlanStatusHintServiceFake(), new WorkloadPreferenceService($store), new FixedShiftService($store, new ShiftConfigService()));

assertSameValue('draft', $service->monthPlan($ebTeam, '2026-08', 'test-eb')['status'] ?? null, 'A new month plan starts as draft.');
assertDomainException(
    static fn() => $service->transitionMonthStatus($assistantTeam, '2026-08', 'planned', 'assistant-a'),
    'Only the responsible EB may transition a month plan.'
);
assertDomainException(
    static fn() => $service->transitionMonthStatus($ebTeam, '2026-08', 'approved', 'test-eb'),
    'A draft plan may not skip the planned state.'
);

$directStore = new MonthPlanStatusStoreFake();
$directService = new ScheduleService($directStore, new ShiftConfigService(), new MonthPlanStatusTeamAccessFake(), new MonthPlanStatusHintServiceFake(), new WorkloadPreferenceService($directStore), new FixedShiftService($directStore, new ShiftConfigService()));
assertSameValue('planned', $directService->transitionMonthStatus($ebTeam, '2026-08', 'planned', 'test-eb'), 'A month can be planned without loading it first.');
assertSameValue('approved', $directService->transitionMonthStatus($ebTeam, '2026-08', 'approved', 'test-eb'), 'A month can be approved without loading it first.');
assertSameValue(2, $directStore->transactionCalls, 'Status transitions run through the store transaction boundary.');
assertSameValue(30, count($directStore->insertedSlots), 'Approval materializes every missing slot before freezing the plan.');

assertSameValue('planned', $service->transitionMonthStatus($ebTeam, '2026-08', 'planned', 'test-eb'), 'EB can mark a draft plan as planned.');
assertSameValue('approved', $service->transitionMonthStatus($ebTeam, '2026-08', 'approved', 'test-eb'), 'EB can approve a planned plan.');
assertDomainException(
    static fn() => $service->addCandidate($ebTeam, '2026-08', 1, 'assistant-a', 'test-eb'),
    'Approved plans reject candidate mutations.'
);
assertDomainException(
    static fn() => $service->saveDayNote($ebTeam, '2026-08', '2026-08-01', 'Gesperrt', 'test-eb'),
    'Approved plans reject note mutations.'
);

$store->updatedSlots = [];
assertSameValue('approved', $service->monthPlan($ebTeam, '2026-08', 'test-eb')['status'] ?? null, 'Approved status remains visible after reload.');
assertSameValue([], $store->updatedSlots, 'Loading an approved plan does not rewrite frozen slot definitions.');
$changedSettingsTeam = new Team('A1', 'ad-ASN-A1', 'Team A1', $assistants, true, ['shifts' => [[
    'key' => 'late',
    'label' => 'Spät neu',
    'startsAt' => '14:00',
    'endsAt' => '20:00',
    'enabled' => true,
]]]);
$approvedSnapshot = $service->monthPlan($changedSettingsTeam, '2026-08', 'test-eb');
assertSameValue(
    ['early'],
    array_column($approvedSnapshot['segments'] ?? [], 'key'),
    'Approved plans should render the frozen slot segments instead of later team settings.'
);

$store->candidatesBySlot = [
    1 => [
        new \OCA\AdPlaner\Model\ShiftCandidate(1, 1, 'assistant-a', 'test-eb'),
        new \OCA\AdPlaner\Model\ShiftCandidate(2, 1, 'former-assistant', 'test-eb'),
    ],
];
$privacyMinimizedSnapshot = $service->monthPlan($ebTeam, '2026-08', 'test-eb');
assertSameValue(
    ['assistant-a'],
    array_column($privacyMinimizedSnapshot['days'][0]['slots'][0]['candidates'] ?? [], 'uid'),
    'Approved plans should expose only currently assignable team members instead of freezing historical person data.'
);

assertSameValue('planned', $service->transitionMonthStatus($ebTeam, '2026-08', 'planned', 'test-eb'), 'EB has an explicit unlock path back to planned.');
$store->candidatesBySlot = [1=>[
    new \OCA\AdPlaner\Model\ShiftCandidate(10,1,'assistant-a','assistant-a',source:'regular'),
    new \OCA\AdPlaner\Model\ShiftCandidate(11,1,'assistant-b','assistant-b',source:'regular'),
]];
$store->regularRules=[
    ['userUid'=>'assistant-a','weekday'=>6,'segmentKey'=>'early'],
    ['userUid'=>'assistant-b','weekday'=>6,'segmentKey'=>'early'],
];
assertDomainException(static fn()=>$service->transitionMonthStatus($ebTeam,'2026-08','approved','test-eb'),'An unresolved duplicate fixed assignment must block approval.');
assertSameValue('planned',$store->monthStatus('A1','2026-08'),'Rejected conflict approval must keep the plan in planned state.');
$store->candidatesBySlot=[];
$store->regularRules=[];
$insertedBeforeConflict = $store->insertedSlots;
$updatedBeforeConflict = $store->updatedSlots;
$store->rejectNextTransition = true;
assertDomainException(
    static fn() => $service->transitionMonthStatus($ebTeam, '2026-08', 'approved', 'test-eb'),
    'A concurrent status conflict rejects approval.'
);
assertSameValue('planned', $store->monthStatus('A1', '2026-08'), 'A failed approval keeps the concurrent month status.');
assertSameValue($insertedBeforeConflict, $store->insertedSlots, 'A failed approval rolls back newly materialized slots.');
assertSameValue($updatedBeforeConflict, $store->updatedSlots, 'A failed approval rolls back slot-definition updates.');
$service->addCandidate($ebTeam, '2026-08', 1, 'assistant-a', 'test-eb');
assertSameValue(1, count($store->added), 'Unlocked plans accept candidate mutations again.');
assertSameValue('draft', $service->transitionMonthStatus($ebTeam, '2026-08', 'draft', 'test-eb'), 'EB can explicitly reset a planned plan to draft.');

echo "AdPlaner month plan status tests passed\n";

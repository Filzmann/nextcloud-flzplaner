<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Service\FixedShiftService;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\AdPlaner\Store\ShiftPlanStore;
use function OCA\AdPlaner\Tests\assertDomainException;
use function OCA\AdPlaner\Tests\assertSameValue;

final class FixedShiftStoreFake extends ShiftPlanStore {
    public array $rules = [];
    public array $savedRules = [];
    public array $materialized = [];
    public array $deleted = [];
    public array $reports = [];
    public array $resolved = [];
    public array $candidates = [];

    public function __construct() {}
    public function regularShiftRulesForTeam(string $teamCode): array { return $this->rules; }
    public function replaceRegularShiftRules(string $teamCode, string $uid, array $rules): void {
        $this->savedRules[] = compact('teamCode', 'uid', 'rules');
        $this->rules = array_values(array_filter($this->rules, static fn(array $rule): bool => ($rule['userUid'] ?? '') !== $uid));
        foreach ($rules as $rule) $this->rules[] = ['userUid'=>$uid, ...$rule];
    }
    public function materializeFixedCandidate(int $slotId, string $uid): void { $this->materialized[] = compact('slotId', 'uid'); }
    public function markFixedCandidateDeleted(int $slotId, string $uid): bool { $this->deleted[] = compact('slotId', 'uid'); return true; }
    public function candidatesForSlotIds(array $slotIds): array { return $this->candidates; }
    public function fixedConflictReports(array $slotIds): array { return $this->reports; }
    public function reportFixedConflict(int $slotId, string $uid): void { $this->reports[$slotId] = ['status'=>'escalated','reportedByUid'=>$uid]; }
    public function resolveFixedConflict(int $slotId, string $keptUid, string $resolvedByUid): void { $this->resolved[] = compact('slotId','keptUid','resolvedByUid'); }
}

$assistants = [
    ['uid'=>'a','displayName'=>'A','isEb'=>false,'canReceiveShifts'=>true],
    ['uid'=>'b','displayName'=>'B','isEb'=>false,'canReceiveShifts'=>true],
    ['uid'=>'eb','displayName'=>'EB','isEb'=>true,'canReceiveShifts'=>false],
];
$settings = ['shifts'=>[
    ['key'=>'early','label'=>'Früh','startsAt'=>'08:00','endsAt'=>'14:10','enabled'=>true],
    ['key'=>'late','label'=>'Spät','startsAt'=>'14:00','endsAt'=>'20:00','enabled'=>true],
]];
$assistantTeam = new Team('A1','ad-ASN-A1','Team A1',$assistants,false,$settings);
$ebTeam = new Team('A1','ad-ASN-A1','Team A1',$assistants,true,$settings);
$store = new FixedShiftStoreFake();
$service = new FixedShiftService($store, new ShiftConfigService());

$saved = $service->savePersonal($assistantTeam, 'a', [
    ['weekday'=>1,'segmentKey'=>'early'],
    ['weekday'=>1,'segmentKey'=>'late'],
]);
assertSameValue(2, count($saved), 'Mehrere regelmäßige Schichten am selben Wochentag müssen trotz Übergabeüberschneidung erlaubt sein.');
assertSameValue('a', $store->savedRules[0]['uid'] ?? null, 'Regeln dürfen nur für die aktuelle Assistenz gespeichert werden.');
assertDomainException(static fn() => $service->savePersonal($ebTeam, 'eb', [['weekday'=>1,'segmentKey'=>'early']]), 'EB-Konten dürfen keine persönlichen Festschichtregeln speichern.');
assertSameValue(1, count($store->savedRules), 'Eine abgewiesene Regeländerung darf nichts speichern.');
try {
    $service->savePersonal($assistantTeam, 'a', [['weekday'=>8,'segmentKey'=>'early']]);
    throw new RuntimeException('Ein ungültiger Wochentag wurde akzeptiert.');
} catch (InvalidArgumentException) {}

$store->rules = [
    ['userUid'=>'a','weekday'=>1,'segmentKey'=>'early'],
    ['userUid'=>'a','weekday'=>1,'segmentKey'=>'late'],
];
$slots = [
    new ShiftSlot(10,'A1','2026-09','2026-09-07','early','Früh','08:00','14:10',true),
    new ShiftSlot(11,'A1','2026-09','2026-09-07','late','Spät','14:00','20:00',true),
];
$service->materializeMonth($assistantTeam, $slots, ['2026-09-07|early'=>['a'=>true]]);
assertSameValue([['slotId'=>11,'uid'=>'a']], $store->materialized, 'Urlaub muss die automatische Festschicht materialisierung verhindern, andere Regeln aber erhalten.');

$fixedA = new ShiftCandidate(1,10,'a','a',preference:'neutral',note:'',source:'regular');
$fixedB = new ShiftCandidate(2,10,'b','b',preference:'neutral',note:'',source:'regular');
$store->candidates = [10=>[$fixedA,$fixedB],11=>[$fixedA]];
$conflicts = $service->conflictsForSlots($assistantTeam, [10,11], 'a');
assertSameValue([10], array_keys($conflicts), 'Nur mehrere feste Personen im selben Slot sind ein Konflikt; überlappende Slots nicht.');

$service->reportConflict($assistantTeam, '2026-09', $slots[0], 'a');
assertSameValue('escalated', $store->reports[10]['status'] ?? null, 'Eine beteiligte Assistenz muss den Konflikt an die EB eskalieren können.');
assertDomainException(static fn() => $service->reportConflict($assistantTeam, '2026-09', $slots[0], 'nobody'), 'Unbeteiligte dürfen keinen Konflikt eskalieren.');

$service->resolveConflict($ebTeam, '2026-09', $slots[0], 'a', 'eb');
assertSameValue('a', $store->resolved[0]['keptUid'] ?? null, 'Die EB muss genau eine feste Zuordnung beibehalten können.');
assertDomainException(static fn() => $service->resolveConflict($assistantTeam, '2026-09', $slots[0], 'a', 'a'), 'Assistenzkräfte dürfen Festschichtkonflikte nicht auflösen.');

assertSameValue(true, $service->deleteOwnOccurrence($assistantTeam, $slots[0], 'a'), 'Eine eigene Festschicht muss als dauerhafte Ausnahme löschbar sein.');

echo "AdPlaner fixed shift service tests passed\n";

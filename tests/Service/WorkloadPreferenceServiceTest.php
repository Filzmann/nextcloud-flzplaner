<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use OCA\FlzPlaner\Model\Team;
use OCA\FlzPlaner\Service\WorkloadPreferenceService;
use OCA\FlzPlaner\Store\ShiftPlanStore;
use function OCA\FlzPlaner\Tests\assertDomainException;
use function OCA\FlzPlaner\Tests\assertSameValue;

final class WorkloadStoreFake extends ShiftPlanStore {
    public array $saved = [];
    public array $limits = [];
    public array $assignments = [];
    public array $rules = [];
    public function __construct() {}
    public function saveWorkloadLimits(string $teamCode, string $uid, array $limits): void { $this->saved[] = compact('teamCode', 'uid', 'limits'); $this->limits[$uid] = $limits; }
    public function workloadLimitsForTeam(string $teamCode): array { return $this->limits; }
    public function candidateDates(string $teamCode, string $from, string $to): array { return $this->assignments; }
    public function regularShiftRulesForTeam(string $teamCode): array { return $this->rules; }
}

$assistants = [
    ['uid'=>'self','displayName'=>'Selbst','isEb'=>false,'canReceiveShifts'=>true],
    ['uid'=>'other','displayName'=>'Andere Person','isEb'=>false,'canReceiveShifts'=>true],
    ['uid'=>'unbounded','displayName'=>'Ohne Grenzen','isEb'=>false,'canReceiveShifts'=>true],
    ['uid'=>'eb','displayName'=>'EB','isEb'=>true,'canReceiveShifts'=>false],
];
$selfTeam = new Team('A1', 'flz-ASN-A1', 'Team A1', $assistants, false, []);
$ebTeam = new Team('A1', 'flz-ASN-A1', 'Team A1', $assistants, true, []);
$store = new WorkloadStoreFake();
$service = new WorkloadPreferenceService($store);

$saved = $service->savePersonal($selfTeam, 'self', '1', '3', '5', '12');
assertSameValue(['weeklyMin'=>1,'weeklyMax'=>3,'monthlyMin'=>5,'monthlyMax'=>12], $saved, 'Gültige persönliche Grenzen müssen normalisiert gespeichert werden.');
assertSameValue('self', $store->saved[0]['uid'] ?? null, 'Grenzen müssen immer der aktuellen Person gehören.');

assertDomainException(static fn() => $service->savePersonal($ebTeam, 'eb', '1', '2', '3', '4'), 'Nicht schichtfähige EB-Konten dürfen keine Schichtgrenzen erhalten.');
assertSameValue(1, count($store->saved), 'Eine abgewiesene EB-Einstellung darf nichts speichern.');
try {
    $service->savePersonal($selfTeam, 'self', '4', '2', '', '');
    throw new RuntimeException('Minimum größer Maximum wurde akzeptiert.');
} catch (InvalidArgumentException) {}

$store->limits = [
    'self' => ['weeklyMin'=>2,'weeklyMax'=>4,'monthlyMin'=>8,'monthlyMax'=>10],
    'other' => ['weeklyMin'=>null,'weeklyMax'=>1,'monthlyMin'=>null,'monthlyMax'=>1],
    'unbounded' => ['weeklyMin'=>0,'weeklyMax'=>0,'monthlyMin'=>0,'monthlyMax'=>0],
];
$store->assignments = [
    ['assistant_uid'=>'self','work_date'=>'2026-09-01','segment_key'=>'early','assignment_source'=>'manual','fixed_deleted'=>false],
    ['assistant_uid'=>'other','work_date'=>'2026-09-02'],
    ['assistant_uid'=>'other','work_date'=>'2026-09-03'],
    ['assistant_uid'=>'unbounded','work_date'=>'2026-09-04'],
];
$store->rules = [['userUid'=>'self','weekday'=>1,'segmentKey'=>'early']];
$overview = $service->overview($ebTeam, '2026-09', 'eb');
assertSameValue('under', $overview[0]['monthStatus'] ?? null, 'Monatlich unter Minimum muss kräftig markiert werden.');
assertSameValue(2, $overview[0]['weeks'][0]['count'] ?? null, 'Eine Kalenderwoche muss einschließlich einer noch nicht materialisierten Festschicht aus dem Vormonat gezählt werden.');
assertSameValue('over', $overview[1]['monthStatus'] ?? null, 'Monatlich über Maximum muss blasser markiert werden.');
assertSameValue('normal', $overview[2]['monthStatus'] ?? null, '0/0 bedeutet keine Monatsgrenze und darf keine Überlastung markieren.');
assertSameValue('normal', $overview[2]['weeks'][0]['status'] ?? null, '0/0 bedeutet keine Wochengrenze und darf keine Überlastung markieren.');

$ownOverview = $service->overview($selfTeam, '2026-09', 'self');
assertSameValue(['self'], array_column($ownOverview, 'uid'), 'Assistenzkräfte dürfen nur die eigene Auslastung erhalten.');

echo "FlzPlaner workload preference service tests passed\n";

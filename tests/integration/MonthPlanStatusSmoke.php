<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/lib/base.php';

use OCA\AdPlaner\Repository\ShiftPlanRepository;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Service\ScheduleService;
use OCA\AdPlaner\Store\ShiftPlanStore;
use OCP\IDBConnection;

$teamCode = 'TXSTATUS';
$month = '2099-02';
$db = \OC::$server->get(IDBConnection::class);
$repository = \OC::$server->get(ShiftPlanRepository::class);
$store = \OC::$server->get(ShiftPlanStore::class);
$service = \OC::$server->get(ScheduleService::class);
$team = new Team($teamCode, 'ad-ASN-' . $teamCode, 'Transaktionstest', [], true, [
    'shifts' => [[
        'key' => 'early',
        'label' => 'Früh',
        'startsAt' => '08:00',
        'endsAt' => '14:00',
        'enabled' => true,
    ]],
]);

$cleanup = static function () use ($db, $teamCode, $month): void {
    $qb = $db->getQueryBuilder();
    $qb->delete('adp_day_notes')
        ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
        ->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($month . '-01')))
        ->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($month . '-28')));
    $qb->executeStatement();

    $qb = $db->getQueryBuilder();
    $qb->delete('adp_shift_slots')
        ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
        ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));
    $qb->executeStatement();

    $qb = $db->getQueryBuilder();
    $qb->delete('adp_month_plans')
        ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
        ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));
    $qb->executeStatement();
};

$cleanup();
try {
    try {
        $store->transactional(static function () use ($store, $teamCode, $month): void {
            $store->insertSlot($teamCode, $month, $month . '-01', 'rollback', 'Rollback', '00:00', '01:00', true);
            throw new RuntimeException('Erwarteter Rollback-Testabbruch.');
        });
        throw new RuntimeException('Die Rollback-Testtransaktion wurde unerwartet abgeschlossen.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Erwarteter Rollback-Testabbruch.') {
            throw $exception;
        }
    }
    if ($store->slotsForMonth($teamCode, $month) !== []) {
        throw new RuntimeException('Eine fehlgeschlagene Transaktion hat einen Slot zurückgelassen.');
    }

    if ($repository->monthStatus($teamCode, $month) !== null) {
        throw new RuntimeException('Ein neuer Monatsplan besitzt unerwartet einen persistierten Status.');
    }
    if ($service->transitionMonthStatus($team, $month, 'planned', 'transaction-test') !== 'planned') {
        throw new RuntimeException('Der erste Statusübergang wurde nicht persistiert.');
    }
    if ($service->transitionMonthStatus($team, $month, 'approved', 'transaction-test') !== 'approved') {
        throw new RuntimeException('Die Genehmigung wurde nicht persistiert.');
    }
    if ($repository->monthStatus($teamCode, $month) !== 'approved') {
        throw new RuntimeException('Der persistierte Monatsstatus ist nicht genehmigt.');
    }
    $slots = $store->slotsForMonth($teamCode, $month);
    if (count($slots) !== 28) {
        throw new RuntimeException('Der genehmigte Februar-Snapshot enthält nicht genau 28 Schichten.');
    }
    foreach ($slots as $slot) {
        if ($slot->segmentKey !== 'early' || !$slot->enabled) {
            throw new RuntimeException('Der genehmigte Snapshot enthält eine unerwartete Schichtdefinition.');
        }
    }

    $qb = $db->getQueryBuilder();
    $qb->select('revision')
        ->from('adp_month_plans')
        ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
        ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));
    $row = $qb->executeQuery()->fetchAssociative();
    if ($row === false || (int)$row['revision'] < 2) {
        throw new RuntimeException('Die Monatsrevision wurde durch Planung und Genehmigung nicht fortgeschrieben.');
    }

    try {
        $service->saveDayNote($team, $month, $month . '-01', 'Darf nicht gespeichert werden', 'transaction-test');
        throw new RuntimeException('Ein genehmigter Plan akzeptierte nachträglich eine Bemerkung.');
    } catch (DomainException) {
    }
    if ($store->dayNotesForMonth($teamCode, $month) !== []) {
        throw new RuntimeException('Der abgewiesene Schreibversuch hat eine Bemerkung zurückgelassen.');
    }
} finally {
    $cleanup();
}

echo "AdPlaner real month plan status persistence smoke passed\n";

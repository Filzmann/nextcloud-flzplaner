<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/lib/base.php';

use OCPDBQueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

$mode = (string)($argv[1] ?? '');
$teamCode = (string)($argv[2] ?? '');
$month = (string)($argv[3] ?? '');
$workDate = (string)($argv[4] ?? '');

if (!preg_match('/^P[0-9]{1,15}$/D', $teamCode)) {
    throw new InvalidArgumentException('Das synthetische Teamkürzel ist ungültig.');
}
if (!preg_match('/^20[0-9]{2}-(?:0[1-9]|1[0-2])$/D', $month)) {
    throw new InvalidArgumentException('Der synthetische Planungsmonat ist ungültig.');
}
if ($workDate !== '' && !preg_match('/^20[0-9]{2}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$/D', $workDate)) {
    throw new InvalidArgumentException('Das synthetische Arbeitsdatum ist ungültig.');
}

$db = \OC::$server->get(IDBConnection::class);

$monthRow = static function () use ($db, $teamCode, $month): ?array {
    $qb = $db->getQueryBuilder();
    $qb->select('revision', 'updated_by_uid', 'updated_at')
        ->from('adp_month_plans')
        ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
        ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));
    $row = $qb->executeQuery()->fetchAssociative();

    if ($row === false) {
        return null;
    }

    return [
        'revision' => (int)$row['revision'],
        'updatedByUid' => (string)$row['updated_by_uid'],
        'updatedAt' => $row['updated_at'] instanceof DateTimeInterface
            ? $row['updated_at']->format('Y-m-d H:i:s')
            : (string)$row['updated_at'],
    ];
};

$rowCount = static function (string $table, array $conditions) use ($db): int {
    $qb = $db->getQueryBuilder();
    $qb->select($qb->func()->count('*', 'row_count'))->from($table);
    foreach ($conditions as $column => $value) {
        $condition = $qb->expr()->eq($column, $qb->createNamedParameter($value));
        $qb->andWhere($condition);
    }

    return (int)$qb->executeQuery()->fetchOne();
};

switch ($mode) {
    case 'assert-no-browser-data':
        $remaining = [];
        foreach (['adp_month_plans', 'adp_day_notes'] as $table) {
            $qb = $db->getQueryBuilder();
            $qb->select($qb->func()->count('*', 'row_count'))
                ->from($table)
                ->where($qb->expr()->like('updated_by_uid', $qb->createNamedParameter('adp-browser-%')));
            $count = (int)$qb->executeQuery()->fetchOne();
            if ($count > 0) {
                $remaining[$table] = $count;
            }
        }
        if ($remaining !== []) {
            throw new RuntimeException('Browser-Smoke-Daten verblieben: ' . json_encode($remaining, JSON_THROW_ON_ERROR));
        }
        echo "Keine Browser-Smoke-Daten verblieben.\n";
        break;

    case 'snapshot':
        $row = $monthRow();
        if ($row === null) {
            throw new RuntimeException('Der synthetische Monatsplan fehlt.');
        }
        echo json_encode($row, JSON_THROW_ON_ERROR) . PHP_EOL;
        break;

    case 'assert-note-present':
        if ($workDate === '' || $rowCount('adp_day_notes', ['team_code' => $teamCode, 'work_date' => $workDate]) !== 1) {
            throw new RuntimeException('Die synthetische Tagesbemerkung wurde nicht eindeutig persistiert.');
        }
        echo "Synthetische Tagesbemerkung ist physisch vorhanden.\n";
        break;

    case 'assert-note-absent':
        if ($workDate === '' || $rowCount('adp_day_notes', ['team_code' => $teamCode, 'work_date' => $workDate]) !== 0) {
            throw new RuntimeException('Die geleerte Tagesbemerkung besitzt weiterhin eine Datenbankzeile.');
        }
        echo "Geleerte Tagesbemerkung besitzt keine Datenbankzeile.\n";
        break;

    case 'cleanup':
        $qb = $db->getQueryBuilder();
        $qb->select('id')->from('adp_shift_slots')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)));
        $slotIds = array_map('intval', $qb->executeQuery()->fetchFirstColumn());
        if ($slotIds !== []) {
            $qb = $db->getQueryBuilder();
            $qb->delete('adp_shift_candidates')
                ->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)));
            $qb->executeStatement();
        }

        foreach ([
            ['adp_day_notes', ['team_code' => $teamCode]],
            ['adp_shift_slots', ['team_code' => $teamCode]],
            ['adp_month_plans', ['team_code' => $teamCode]],
            ['adp_team_settings', ['team_code' => $teamCode]],
        ] as [$table, $conditions]) {
            $qb = $db->getQueryBuilder();
            $qb->delete($table);
            foreach ($conditions as $column => $value) {
                $qb->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($value)));
            }
            $qb->executeStatement();
        }
        echo "Synthetische AdPlaner-Daten wurden bereinigt.\n";
        break;

    case 'assert-clean':
        $remaining = [];
        foreach ([
            'adp_day_notes' => ['team_code' => $teamCode],
            'adp_shift_slots' => ['team_code' => $teamCode],
            'adp_month_plans' => ['team_code' => $teamCode],
            'adp_team_settings' => ['team_code' => $teamCode],
        ] as $table => $conditions) {
            $count = $rowCount($table, $conditions);
            if ($count > 0) {
                $remaining[$table] = $count;
            }
        }
        if ($remaining !== []) {
            throw new RuntimeException('Synthetische AdPlaner-Daten verblieben: ' . json_encode($remaining, JSON_THROW_ON_ERROR));
        }
        echo "Keine synthetischen AdPlaner-Daten verblieben.\n";
        break;

    default:
        throw new InvalidArgumentException('Unbekannter Prüfmodus.');
}

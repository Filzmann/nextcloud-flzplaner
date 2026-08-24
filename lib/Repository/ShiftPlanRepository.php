<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Repository;

use DateTimeImmutable;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ShiftPlanRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function transactional(callable $operation): mixed {
        if ($this->db->inTransaction()) {
            return $operation();
        }

        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{candidates:list<array<string,mixed>>,dayNotes:list<array<string,mixed>>,monthPlans:list<array<string,mixed>>} */
    public function personalDataForUid(string $uid, int $limit): array {
        $candidateQuery = $this->db->getQueryBuilder();
        $candidateQuery->select('c.id', 'c.assistant_uid', 'c.created_by_uid', 'c.created_at', 's.team_code', 's.work_date', 's.label', 's.starts_at', 's.ends_at')
            ->from('adp_shift_candidates', 'c')
            ->innerJoin('c', 'adp_shift_slots', 's', $candidateQuery->expr()->eq('s.id', 'c.slot_id'))
            ->where($candidateQuery->expr()->orX(
                $candidateQuery->expr()->eq('c.assistant_uid', $candidateQuery->createNamedParameter($uid)),
                $candidateQuery->expr()->eq('c.created_by_uid', $candidateQuery->createNamedParameter($uid)),
            ))
            ->orderBy('c.created_at', 'ASC')
            ->setMaxResults($limit);

        $noteQuery = $this->db->getQueryBuilder();
        $noteQuery->select('id', 'team_code', 'work_date', 'note', 'updated_at')
            ->from('adp_day_notes')
            ->where($noteQuery->expr()->eq('updated_by_uid', $noteQuery->createNamedParameter($uid)))
            ->orderBy('updated_at', 'ASC')
            ->setMaxResults($limit);

        $monthQuery = $this->db->getQueryBuilder();
        $monthQuery->select('id', 'team_code', 'plan_month', 'status', 'updated_at')
            ->from('adp_month_plans')
            ->where($monthQuery->expr()->eq('updated_by_uid', $monthQuery->createNamedParameter($uid)))
            ->orderBy('updated_at', 'ASC')
            ->setMaxResults($limit);

        return [
            'candidates' => $candidateQuery->executeQuery()->fetchAllAssociative(),
            'dayNotes' => $noteQuery->executeQuery()->fetchAllAssociative(),
            'monthPlans' => $monthQuery->executeQuery()->fetchAllAssociative(),
        ];
    }

    public function findSlotsForMonth(string $teamCode, string $month): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_shift_slots')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)))
            ->orderBy('work_date', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    public function findSlot(int $slotId, string $teamCode, string $month): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_shift_slots')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));

        $row = $qb->executeQuery()->fetchAssociative();

        return $row === false ? null : $row;
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
        $now = new DateTimeImmutable();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('adp_shift_slots')
            ->values([
                'team_code' => $qb->createNamedParameter($teamCode),
                'plan_month' => $qb->createNamedParameter($month),
                'work_date' => $qb->createNamedParameter($workDate),
                'segment_key' => $qb->createNamedParameter($segmentKey),
                'label' => $qb->createNamedParameter($label),
                'starts_at' => $qb->createNamedParameter($startsAt),
                'ends_at' => $qb->createNamedParameter($endsAt),
                'enabled' => $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ]);
        $qb->executeStatement();

        return $qb->getLastInsertId();
    }

    public function updateSlotDefinition(int $slotId, string $label, string $startsAt, string $endsAt, bool $enabled): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_shift_slots')
            ->set('label', $qb->createNamedParameter($label))
            ->set('starts_at', $qb->createNamedParameter($startsAt))
            ->set('ends_at', $qb->createNamedParameter($endsAt))
            ->set('enabled', $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function candidatesForSlotIds(array $slotIds): array {
        $slotIds = array_values(array_filter(array_map('intval', $slotIds), static fn(int $id): bool => $id > 0));
        if ($slotIds === []) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_shift_candidates')
            ->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)))
            ->orderBy('created_at', 'ASC')
            ->addOrderBy('assistant_uid', 'ASC');

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $bySlot = [];
        foreach ($rows as $row) {
            $slotId = (int)$row['slot_id'];
            $bySlot[$slotId][] = $row;
        }

        return $bySlot;
    }

    public function addCandidate(int $slotId, string $assistantUid, string $createdByUid): void {
        if ($this->candidateExists($slotId, $assistantUid)) {
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('adp_shift_candidates')
            ->values([
                'slot_id' => $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT),
                'assistant_uid' => $qb->createNamedParameter($assistantUid),
                'created_by_uid' => $qb->createNamedParameter($createdByUid),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ]);
        try {
            $qb->executeStatement();
        } catch (Exception $exception) {
            if ($exception->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                throw $exception;
            }
        }
    }

    public function removeCandidate(int $slotId, string $assistantUid): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('adp_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)));
        $qb->executeStatement();
    }

    public function dayNotes(string $teamCode, string $month): array {
        $from = $month . '-01';
        $to = (new \DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d');

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($from)))
            ->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($to)));

        $notes = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $notes[(string)$row['work_date']] = (string)($row['note'] ?? '');
        }

        return $notes;
    }

    public function saveDayNote(string $teamCode, string $workDate, string $note, string $updatedByUid): void {
        $existing = $this->findDayNote($teamCode, $workDate);
        $now = new DateTimeImmutable();

        if ($existing === null) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('adp_day_notes')
                ->values([
                    'team_code' => $qb->createNamedParameter($teamCode),
                    'work_date' => $qb->createNamedParameter($workDate),
                    'note' => $qb->createNamedParameter($note),
                    'updated_by_uid' => $qb->createNamedParameter($updatedByUid),
                    'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
                ]);
            try {
                $qb->executeStatement();
                return;
            } catch (Exception $exception) {
                if ($exception->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                    throw $exception;
                }
            }
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_day_notes')
            ->set('note', $qb->createNamedParameter($note))
            ->set('updated_by_uid', $qb->createNamedParameter($updatedByUid))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));
        $qb->executeStatement();
    }

    public function deleteDayNote(string $teamCode, string $workDate): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('adp_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));
        $qb->executeStatement();
    }

    public function monthStatus(string $teamCode, string $month): ?string {
        $qb = $this->db->getQueryBuilder();
        $qb->select('status')
            ->from('adp_month_plans')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));
        $row = $qb->executeQuery()->fetchAssociative();

        return $row === false ? null : (string)$row['status'];
    }

    public function ensureMonthStatus(string $teamCode, string $month, string $updatedByUid): void {
        if ($this->monthStatus($teamCode, $month) !== null) {
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('adp_month_plans')->values([
            'team_code' => $qb->createNamedParameter($teamCode),
            'plan_month' => $qb->createNamedParameter($month),
            'status' => $qb->createNamedParameter('draft'),
            'revision' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
            'updated_by_uid' => $qb->createNamedParameter($updatedByUid),
            'updated_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
        ]);

        try {
            $qb->executeStatement();
        } catch (Exception $exception) {
            if ($exception->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                throw $exception;
            }
        }
    }

    public function lockMonthStatus(
        string $teamCode,
        string $month,
        array $expectedStatuses
    ): ?string {
        $expectedStatuses = array_values(array_unique(array_map('strval', $expectedStatuses)));
        if ($expectedStatuses === []) {
            return null;
        }

        $qb = $this->db->getQueryBuilder();
        $statusConditions = array_map(
            fn(string $status) => $qb->expr()->eq('status', $qb->createNamedParameter($status)),
            $expectedStatuses
        );
        $qb->update('adp_month_plans')
            ->set('revision', $qb->createFunction('revision + 1'))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)))
            ->andWhere($qb->expr()->orX(...$statusConditions));

        if ($qb->executeStatement() !== 1) {
            return null;
        }

        return $this->monthStatus($teamCode, $month);
    }

    public function transitionMonthStatus(
        string $teamCode,
        string $month,
        string $expectedStatus,
        string $targetStatus,
        string $updatedByUid
    ): bool {
        $now = new DateTimeImmutable();
        if ($this->monthStatus($teamCode, $month) === null) {
            if ($expectedStatus !== 'draft') {
                return false;
            }
            $qb = $this->db->getQueryBuilder();
            $qb->insert('adp_month_plans')->values([
                'team_code' => $qb->createNamedParameter($teamCode),
                'plan_month' => $qb->createNamedParameter($month),
                'status' => $qb->createNamedParameter($targetStatus),
                'revision' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
                'updated_by_uid' => $qb->createNamedParameter($updatedByUid),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ]);

            return $qb->executeStatement() === 1;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_month_plans')
            ->set('status', $qb->createNamedParameter($targetStatus))
            ->set('updated_by_uid', $qb->createNamedParameter($updatedByUid))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)))
            ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($expectedStatus)));

        return $qb->executeStatement() === 1;
    }

    private function candidateExists(int $slotId, string $assistantUid): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('adp_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
            ->setMaxResults(1);

        return $qb->executeQuery()->fetchAssociative() !== false;
    }

    private function findDayNote(string $teamCode, string $workDate): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));

        $row = $qb->executeQuery()->fetchAssociative();

        return $row === false ? null : $row;
    }
}

<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Repository;

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

    /** @return array<string,list<array<string,mixed>>> */
    public function personalDataForUid(string $uid, int $limit): array {
        $candidateQuery = $this->db->getQueryBuilder();
        $candidateQuery->select('c.id', 'c.assistant_uid', 'c.created_by_uid', 'c.created_at', 'c.preference', 'c.candidate_note', 'c.metadata_updated_at', 'c.assignment_source', 'c.fixed_deleted', 'c.fixed_modified', 's.team_code', 's.work_date', 's.label', 's.starts_at', 's.ends_at')
            ->from('flz_planer_shift_candidates', 'c')
            ->innerJoin('c', 'flz_planer_shift_slots', 's', $candidateQuery->expr()->eq('s.id', 'c.slot_id'))
            ->where($candidateQuery->expr()->orX(
                $candidateQuery->expr()->eq('c.assistant_uid', $candidateQuery->createNamedParameter($uid)),
                $candidateQuery->expr()->eq('c.created_by_uid', $candidateQuery->createNamedParameter($uid)),
            ))
            ->orderBy('c.created_at', 'ASC')
            ->setMaxResults($limit);

        $noteQuery = $this->db->getQueryBuilder();
        $noteQuery->select('id', 'team_code', 'work_date', 'note', 'updated_at')
            ->from('flz_planer_day_notes')
            ->where($noteQuery->expr()->eq('updated_by_uid', $noteQuery->createNamedParameter($uid)))
            ->orderBy('updated_at', 'ASC')
            ->setMaxResults($limit);

        $monthQuery = $this->db->getQueryBuilder();
        $monthQuery->select('id', 'team_code', 'plan_month', 'status', 'updated_at')
            ->from('flz_planer_month_plans')
            ->where($monthQuery->expr()->eq('updated_by_uid', $monthQuery->createNamedParameter($uid)))
            ->orderBy('updated_at', 'ASC')
            ->setMaxResults($limit);

        $limitsQuery = $this->db->getQueryBuilder();
        $limitsQuery->select('id', 'team_code', 'weekly_min', 'weekly_max', 'monthly_min', 'monthly_max', 'updated_at')
            ->from('flz_planer_workload_limits')
            ->where($limitsQuery->expr()->eq('user_uid', $limitsQuery->createNamedParameter($uid)))
            ->orderBy('updated_at', 'ASC')
            ->setMaxResults($limit);

        $rulesQuery = $this->db->getQueryBuilder();
        $rulesQuery->select('id','team_code','weekday','segment_key','updated_at')->from('flz_planer_regular_shifts')
            ->where($rulesQuery->expr()->eq('user_uid',$rulesQuery->createNamedParameter($uid)))
            ->orderBy('updated_at','ASC')->setMaxResults($limit);

        $conflictsQuery = $this->db->getQueryBuilder();
        $conflictsQuery->select('id','slot_id','status','reported_at','resolved_at')->from('flz_planer_fixed_conflicts')
            ->where($conflictsQuery->expr()->orX(
                $conflictsQuery->expr()->eq('reported_by_uid',$conflictsQuery->createNamedParameter($uid)),
                $conflictsQuery->expr()->eq('resolved_by_uid',$conflictsQuery->createNamedParameter($uid)),
                $conflictsQuery->expr()->eq('kept_uid',$conflictsQuery->createNamedParameter($uid)),
            ))->orderBy('reported_at','ASC')->setMaxResults($limit);

        return [
            'candidates' => $candidateQuery->executeQuery()->fetchAllAssociative(),
            'dayNotes' => $noteQuery->executeQuery()->fetchAllAssociative(),
            'monthPlans' => $monthQuery->executeQuery()->fetchAllAssociative(),
            'workloadLimits' => $limitsQuery->executeQuery()->fetchAllAssociative(),
            'regularShifts' => $rulesQuery->executeQuery()->fetchAllAssociative(),
            'fixedConflicts' => $conflictsQuery->executeQuery()->fetchAllAssociative(),
        ];
    }

    public function findSlotsForMonth(string $teamCode, string $month): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('flz_planer_shift_slots')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)))
            ->orderBy('work_date', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    public function findSlot(int $slotId, string $teamCode, string $month): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('flz_planer_shift_slots')
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
        $qb->insert('flz_planer_shift_slots')
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
        $qb->update('flz_planer_shift_slots')
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
            ->from('flz_planer_shift_candidates')
            ->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)))
            ->andWhere($qb->expr()->eq('fixed_deleted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
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
        $qb->insert('flz_planer_shift_candidates')
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
        $qb->delete('flz_planer_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)));
        $qb->executeStatement();
    }

    public function markFixedCandidateDeleted(int $slotId, string $assistantUid): bool {
        $qb = $this->db->getQueryBuilder();
        return $qb->update('flz_planer_shift_candidates')
            ->set('fixed_deleted', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
            ->andWhere($qb->expr()->eq('assignment_source', $qb->createNamedParameter('regular')))
            ->andWhere($qb->expr()->eq('fixed_deleted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->executeStatement() === 1;
    }

    public function materializeFixedCandidate(int $slotId, string $assistantUid): void {
        $existing = $this->findCandidate($slotId, $assistantUid);
        if ($existing !== null) {
            if ((bool)($existing['fixed_deleted'] ?? false) || (bool)($existing['fixed_modified'] ?? false)) return;
            $qb = $this->db->getQueryBuilder();
            $qb->update('flz_planer_shift_candidates')
                ->set('assignment_source', $qb->createNamedParameter('regular'))
                ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
                ->executeStatement();
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('flz_planer_shift_candidates')->values([
            'slot_id'=>$qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT),
            'assistant_uid'=>$qb->createNamedParameter($assistantUid),
            'created_by_uid'=>$qb->createNamedParameter($assistantUid),
            'assignment_source'=>$qb->createNamedParameter('regular'),
            'fixed_deleted'=>$qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
            'created_at'=>$qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
        ]);
        try { $qb->executeStatement(); }
        catch (Exception $exception) {
            if ($exception->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) throw $exception;
            $this->materializeFixedCandidate($slotId,$assistantUid);
        }
    }

    public function regularShiftRulesForTeam(string $teamCode): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('flz_planer_regular_shifts')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->orderBy('weekday', 'ASC')->addOrderBy('segment_key', 'ASC')->addOrderBy('user_uid', 'ASC');
        return $qb->executeQuery()->fetchAllAssociative();
    }

    public function replaceRegularShiftRules(string $teamCode, string $uid, array $rules): void {
        $this->transactional(function () use ($teamCode, $uid, $rules): void {
            $delete = $this->db->getQueryBuilder();
            $delete->delete('flz_planer_regular_shifts')
                ->where($delete->expr()->eq('team_code', $delete->createNamedParameter($teamCode)))
                ->andWhere($delete->expr()->eq('user_uid', $delete->createNamedParameter($uid)))
                ->executeStatement();
            foreach ($rules as $rule) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert('flz_planer_regular_shifts')->values([
                    'team_code'=>$qb->createNamedParameter($teamCode),
                    'user_uid'=>$qb->createNamedParameter($uid),
                    'weekday'=>$qb->createNamedParameter((int)$rule['weekday'], IQueryBuilder::PARAM_INT),
                    'segment_key'=>$qb->createNamedParameter((string)$rule['segmentKey']),
                    'updated_at'=>$qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
                ])->executeStatement();
            }
        });
    }

    public function fixedConflictReports(array $slotIds): array {
        $slotIds = array_values(array_filter(array_map('intval', $slotIds), static fn(int $id): bool => $id > 0));
        if ($slotIds === []) return [];
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('flz_planer_fixed_conflicts')
            ->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)));
        $result = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) $result[(int)$row['slot_id']] = $row;
        return $result;
    }

    public function reportFixedConflict(int $slotId, string $uid): void {
        $existing = $this->fixedConflictReports([$slotId])[$slotId] ?? null;
        if ($existing === null) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('flz_planer_fixed_conflicts')->values([
                'slot_id'=>$qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT),
                'status'=>$qb->createNamedParameter('escalated'),
                'reported_by_uid'=>$qb->createNamedParameter($uid),
                'reported_at'=>$qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ])->executeStatement();
            return;
        }
        $qb = $this->db->getQueryBuilder();
        $qb->update('flz_planer_fixed_conflicts')->set('status',$qb->createNamedParameter('escalated'))
            ->set('reported_by_uid',$qb->createNamedParameter($uid))
            ->set('reported_at',$qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->set('kept_uid',$qb->createNamedParameter(null))->set('resolved_by_uid',$qb->createNamedParameter(null))->set('resolved_at',$qb->createNamedParameter(null))
            ->where($qb->expr()->eq('slot_id',$qb->createNamedParameter($slotId,IQueryBuilder::PARAM_INT)))->executeStatement();
    }

    public function resolveFixedConflict(int $slotId, string $keptUid, string $resolvedByUid): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('flz_planer_shift_candidates')
            ->set('assignment_source',$qb->createNamedParameter('manual'))
            ->set('fixed_modified',$qb->createNamedParameter(true,IQueryBuilder::PARAM_BOOL))
            ->where($qb->expr()->eq('slot_id',$qb->createNamedParameter($slotId,IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assignment_source',$qb->createNamedParameter('regular')))
            ->andWhere($qb->expr()->neq('assistant_uid',$qb->createNamedParameter($keptUid)))
            ->executeStatement();
        $report = $this->fixedConflictReports([$slotId])[$slotId] ?? null;
        if ($report === null) return;
        $update = $this->db->getQueryBuilder();
        $update->update('flz_planer_fixed_conflicts')->set('status',$update->createNamedParameter('resolved'))
            ->set('kept_uid',$update->createNamedParameter($keptUid))->set('resolved_by_uid',$update->createNamedParameter($resolvedByUid))
            ->set('resolved_at',$update->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($update->expr()->eq('slot_id',$update->createNamedParameter($slotId,IQueryBuilder::PARAM_INT)))->executeStatement();
    }

    public function findCandidate(int $slotId, string $assistantUid): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('flz_planer_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)));
        $row = $qb->executeQuery()->fetchAssociative();

        return $row === false ? null : $row;
    }

    public function deletedFixedSlotIds(array $slotIds,string $uid): array {
        $slotIds=array_values(array_filter(array_map('intval',$slotIds),static fn(int $id):bool=>$id>0));
        if($slotIds===[]||$uid==='')return [];
        $qb=$this->db->getQueryBuilder();
        $qb->select('slot_id')->from('flz_planer_shift_candidates')
            ->where($qb->expr()->in('slot_id',$qb->createNamedParameter($slotIds,IQueryBuilder::PARAM_INT_ARRAY)))
            ->andWhere($qb->expr()->eq('assistant_uid',$qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->eq('assignment_source',$qb->createNamedParameter('regular')))
            ->andWhere($qb->expr()->eq('fixed_deleted',$qb->createNamedParameter(true,IQueryBuilder::PARAM_BOOL)));
        return array_map('intval',$qb->executeQuery()->fetchFirstColumn());
    }

    public function updateCandidateMetadata(int $slotId, string $assistantUid, string $preference, string $note): bool {
        $qb = $this->db->getQueryBuilder();
        $updated = $qb->update('flz_planer_shift_candidates')
            ->set('preference', $qb->createNamedParameter($preference))
            ->set('candidate_note', $qb->createNamedParameter($note === '' ? null : $note))
            ->set('metadata_updated_at', $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
            ->executeStatement();

        return $updated === 1;
    }

    public function workloadLimitsForTeam(string $teamCode): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('flz_planer_workload_limits')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)));
        $limits = [];
        foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
            $limits[(string)$row['user_uid']] = $row;
        }

        return $limits;
    }

    public function saveWorkloadLimits(string $teamCode, string $uid, array $limits): void {
        $existing = $this->workloadLimit($teamCode, $uid);
        $now = new DateTimeImmutable();
        if ($existing === null) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('flz_planer_workload_limits')->values([
                'team_code' => $qb->createNamedParameter($teamCode),
                'user_uid' => $qb->createNamedParameter($uid),
                'weekly_min' => $qb->createNamedParameter($limits['weeklyMin'], IQueryBuilder::PARAM_INT),
                'weekly_max' => $qb->createNamedParameter($limits['weeklyMax'], IQueryBuilder::PARAM_INT),
                'monthly_min' => $qb->createNamedParameter($limits['monthlyMin'], IQueryBuilder::PARAM_INT),
                'monthly_max' => $qb->createNamedParameter($limits['monthlyMax'], IQueryBuilder::PARAM_INT),
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
        $qb->update('flz_planer_workload_limits')
            ->set('weekly_min', $qb->createNamedParameter($limits['weeklyMin'], IQueryBuilder::PARAM_INT))
            ->set('weekly_max', $qb->createNamedParameter($limits['weeklyMax'], IQueryBuilder::PARAM_INT))
            ->set('monthly_min', $qb->createNamedParameter($limits['monthlyMin'], IQueryBuilder::PARAM_INT))
            ->set('monthly_max', $qb->createNamedParameter($limits['monthlyMax'], IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($uid)))
            ->executeStatement();
    }

    public function candidateDates(string $teamCode, string $from, string $to): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('c.assistant_uid', 'c.assignment_source', 'c.fixed_deleted', 's.work_date', 's.segment_key')->from('flz_planer_shift_candidates', 'c')
            ->innerJoin('c', 'flz_planer_shift_slots', 's', $qb->expr()->eq('s.id', 'c.slot_id'))
            ->where($qb->expr()->eq('s.team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('s.enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->gte('s.work_date', $qb->createNamedParameter($from)))
            ->andWhere($qb->expr()->lte('s.work_date', $qb->createNamedParameter($to)));

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /** @return list<array<string,mixed>> */
    public function candidateIntervalsForEmployee(string $employeeUid, DateTimeImmutable $start, DateTimeImmutable $end): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('c.assistant_uid', 'c.fixed_deleted', 's.work_date', 's.starts_at', 's.ends_at')
            ->from('flz_planer_shift_candidates', 'c')
            ->innerJoin('c', 'flz_planer_shift_slots', 's', $qb->expr()->eq('s.id', 'c.slot_id'))
            ->where($qb->expr()->eq('c.assistant_uid', $qb->createNamedParameter($employeeUid)))
            ->andWhere($qb->expr()->eq('c.fixed_deleted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
            ->andWhere($qb->expr()->eq('s.enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->gte('s.work_date', $qb->createNamedParameter($start->modify('-1 day')->format('Y-m-d'))))
            ->andWhere($qb->expr()->lte('s.work_date', $qb->createNamedParameter($end->format('Y-m-d'))));

        return $qb->executeQuery()->fetchAllAssociative();
    }

    private function workloadLimit(string $teamCode, string $uid): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from('flz_planer_workload_limits')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($uid)));
        $row = $qb->executeQuery()->fetchAssociative();

        return $row === false ? null : $row;
    }

    public function dayNotes(string $teamCode, string $month): array {
        $from = $month . '-01';
        $to = (new \DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d');

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('flz_planer_day_notes')
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
            $qb->insert('flz_planer_day_notes')
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
        $qb->update('flz_planer_day_notes')
            ->set('note', $qb->createNamedParameter($note))
            ->set('updated_by_uid', $qb->createNamedParameter($updatedByUid))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));
        $qb->executeStatement();
    }

    public function deleteDayNote(string $teamCode, string $workDate): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('flz_planer_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));
        $qb->executeStatement();
    }

    public function monthStatus(string $teamCode, string $month): ?string {
        $qb = $this->db->getQueryBuilder();
        $qb->select('status')
            ->from('flz_planer_month_plans')
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
        $qb->insert('flz_planer_month_plans')->values([
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
        $qb->update('flz_planer_month_plans')
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
            $qb->insert('flz_planer_month_plans')->values([
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
        $qb->update('flz_planer_month_plans')
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
            ->from('flz_planer_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
            ->setMaxResults(1);

        return $qb->executeQuery()->fetchAssociative() !== false;
    }

    private function findDayNote(string $teamCode, string $workDate): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('flz_planer_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));

        $row = $qb->executeQuery()->fetchAssociative();

        return $row === false ? null : $row;
    }
}

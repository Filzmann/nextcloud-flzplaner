<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Store;

use OCA\AdPlaner\Model\DayNote;
use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Repository\ShiftPlanRepository;

class ShiftPlanStore {
    public function __construct(
        private ShiftPlanRepository $repository
    ) {
    }

    public function transactional(callable $operation): mixed {
        return $this->repository->transactional($operation);
    }

    public function slotsForMonth(string $teamCode, string $month): array {
        return ShiftSlot::get_all($this->repository->findSlotsForMonth($teamCode, $month));
    }

    public function slotForMonth(int $slotId, string $teamCode, string $month): ?ShiftSlot {
        $row = $this->repository->findSlot($slotId, $teamCode, $month);

        return ShiftSlot::get($row);
    }

    public function candidatesForSlotIds(array $slotIds): array {
        $rowsBySlot = $this->repository->candidatesForSlotIds($slotIds);
        $candidatesBySlot = [];

        foreach ($rowsBySlot as $slotId => $rows) {
            $candidatesBySlot[(int)$slotId] = ShiftCandidate::get_all($rows);
        }

        return $candidatesBySlot;
    }

    public function dayNotesForMonth(string $teamCode, string $month): array {
        $notes = [];
        foreach ($this->repository->dayNotes($teamCode, $month) as $workDate => $note) {
            $notes[(string)$workDate] = new DayNote($teamCode, (string)$workDate, (string)$note);
        }

        return $notes;
    }

    public function monthStatus(string $teamCode, string $month): string {
        return $this->repository->monthStatus($teamCode, $month) ?? 'draft';
    }

    public function ensureMonthStatus(string $teamCode, string $month, string $updatedByUid): void {
        $this->repository->ensureMonthStatus($teamCode, $month, $updatedByUid);
    }

    public function lockMonthStatus(string $teamCode, string $month, array $expectedStatuses): ?string {
        return $this->repository->lockMonthStatus($teamCode, $month, $expectedStatuses);
    }

    public function transitionMonthStatus(
        string $teamCode,
        string $month,
        string $expectedStatus,
        string $targetStatus,
        string $updatedByUid
    ): bool {
        return $this->repository->transitionMonthStatus(
            $teamCode,
            $month,
            $expectedStatus,
            $targetStatus,
            $updatedByUid
        );
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
        return $this->repository->insertSlot(
            $teamCode,
            $month,
            $workDate,
            $segmentKey,
            $label,
            $startsAt,
            $endsAt,
            $enabled
        );
    }

    public function updateSlotDefinition(int $slotId, string $label, string $startsAt, string $endsAt, bool $enabled): void {
        $this->repository->updateSlotDefinition($slotId, $label, $startsAt, $endsAt, $enabled);
    }

    public function addCandidate(int $slotId, string $assistantUid, string $createdByUid): void {
        $this->repository->addCandidate($slotId, $assistantUid, $createdByUid);
    }

    public function removeCandidate(int $slotId, string $assistantUid): void {
        $this->repository->removeCandidate($slotId, $assistantUid);
    }

    public function saveDayNote(string $teamCode, string $workDate, string $note, string $updatedByUid): void {
        $this->repository->saveDayNote($teamCode, $workDate, $note, $updatedByUid);
    }

    public function deleteDayNote(string $teamCode, string $workDate): void {
        $this->repository->deleteDayNote($teamCode, $workDate);
    }
}

<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Store\ShiftPlanStore;

class ScheduleService {
    private const STATUS_DRAFT = 'draft';
    private const STATUS_PLANNED = 'planned';
    private const STATUS_APPROVED = 'approved';
    private const MAX_DAY_NOTE_LENGTH = 2000;
    private const MAX_CANDIDATE_NOTE_LENGTH = 500;

    public function __construct(
        private ShiftPlanStore $store,
        private ShiftConfigService $shiftConfig,
        private TeamAccessService $teamAccess,
        private PlanningHintService $planningHints,
        private WorkloadPreferenceService $workloadPreferences,
        private FixedShiftService $fixedShifts
    ) {
    }

    public function monthPlan(Team $team, string $month, string $currentUid): array {
        $month = $this->shiftConfig->normalizeMonth($month);
        $assignableUids = $team->assignableAssistantUidMap();
        $planningSegments = array_values(array_filter($this->shiftConfig->segments($team->settings), static fn(array $segment): bool => $segment['enabled']));
        $planningContext = $this->planningHints->contextForMonth($month, array_keys($assignableUids), $planningSegments);
        $status = $this->store->monthStatus($team->code, $month);
        if ($status !== self::STATUS_APPROVED) {
            $this->store->ensureMonthStatus($team->code, $month, $currentUid);
            $status = $this->store->transactional(function () use ($team, $month, $planningContext): string {
                $lockedStatus = $this->store->lockMonthStatus(
                    $team->code,
                    $month,
                    [self::STATUS_DRAFT, self::STATUS_PLANNED]
                );
                if ($lockedStatus === null) {
                    return $this->store->monthStatus($team->code, $month);
                }

                $this->ensureMonthSlots($team, $month);
                $this->fixedShifts->materializeMonth($team, $this->store->slotsForMonth($team->code, $month), $planningContext['unavailable']);

                return $lockedStatus;
            });
        }

        $slots = $this->store->slotsForMonth($team->code, $month);
        $enabledSlots = array_values(array_filter($slots, static fn(ShiftSlot $slot): bool => $slot->enabled));
        $segments = $status === self::STATUS_APPROVED
            ? $this->segmentsFromFrozenSlots($enabledSlots)
            : array_values(array_filter($this->shiftConfig->segments($team->settings), static fn(array $segment): bool => $segment['enabled']));
        $candidatesBySlot = $this->store->candidatesForSlotIds(array_map(static fn(ShiftSlot $slot): int => $slot->id, $enabledSlots));
        $fixedConflicts = $this->fixedShifts->conflictsForSlots($team, array_map(static fn(ShiftSlot $slot): int => $slot->id, $enabledSlots), $currentUid);
        $deletedFixedSlots=array_flip($this->store->deletedFixedSlotIds(array_map(static fn(ShiftSlot $slot):int=>$slot->id,$enabledSlots),$currentUid));
        $assistantLabels = $team->assistantLabelMap();
        $notes = $this->store->dayNotesForMonth($team->code, $month);
        $hints = $planningContext['hints'];
        $workload = $this->workloadPreferences->overview($team, $month, $currentUid);
        $workloadByUid = array_column($workload, null, 'uid');
        $slotsByDate = [];

        foreach ($enabledSlots as $slot) {
            $slotCandidates = array_values(array_filter(
                $candidatesBySlot[$slot->id] ?? [],
                static fn(ShiftCandidate $candidate): bool => isset($assignableUids[$candidate->assistantUid])
            ));
            $slot->candidates = $this->candidatePayload($slotCandidates, $assistantLabels, $currentUid, $slot->workDate, $slot->segmentKey, $workloadByUid, $planningContext['unavailable']);
            $slotPayload = $slot->toArray();
            $slotPayload['fixedConflict'] = $fixedConflicts[$slot->id] ?? null;
            $slotPayload['selfUnavailable'] = isset($deletedFixedSlots[$slot->id]);
            $slotsByDate[$slot->workDate][] = $slotPayload;
        }

        $days = [];
        foreach ($this->shiftConfig->monthDays($month) as $day) {
            $date = $day['date'];
            $days[] = [
                'date' => $date,
                'dayOfMonth' => $day['dayOfMonth'],
                'weekday' => $day['weekday'],
                'weekLabel' => 'KW ' . (new \DateTimeImmutable($date))->format('W'),
                'slots' => $slotsByDate[$date] ?? [],
                'note' => isset($notes[$date]) ? $notes[$date]->note : '',
                'hints' => array_map(static function (array $hint) use ($assistantLabels): array {
                    $employeeUid = (string)($hint['employeeUid'] ?? '');
                    $hint['displayName'] = $assistantLabels[$employeeUid] ?? '';
                    unset($hint['employeeUid']);
                    return $hint;
                }, $hints[$date] ?? []),
            ];
        }

        return [
            'team' => $team->toArray(),
            'month' => $month,
            'status' => $status,
            'segments' => $segments,
            'days' => $days,
            'workload' => $workload,
        ];
    }

    public function addCandidate(Team $team, string $month, int $slotId, string $targetUid, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        if ($targetUid === '') {
            if ($team->isEb) {
                throw new \DomainException('Bitte eine Assistenzkraft auswählen.');
            }

            $targetUid = $currentUid;
        }

        $this->assertCandidateMutationAllowed($team, $targetUid, $currentUid);
        $this->assertAssignableAssistantInTeam($team, $targetUid);
        $requestedSlot = $this->requireSlot($slotId, $team->code, $month);
        $this->planningHints->assertAvailableForSlot($requestedSlot, $targetUid);

        $this->withMutableMonth($team->code, $month, $currentUid, function () use ($slotId, $team, $month, $targetUid, $currentUid): void {
            $slot = $this->requireSlot($slotId, $team->code, $month);
            $this->store->addCandidate($slot->id, $targetUid, $currentUid);
        });
    }

    public function removeCandidate(Team $team, string $month, int $slotId, string $targetUid, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        $targetUid = $targetUid === '' ? $currentUid : $targetUid;

        $this->assertCandidateMutationAllowed($team, $targetUid, $currentUid);
        $this->assertAssignableAssistantInTeam($team, $targetUid);

        $this->withMutableMonth($team->code, $month, $currentUid, function () use ($slotId, $team, $month, $targetUid, $currentUid): void {
            $slot = $this->requireSlot($slotId, $team->code, $month);
            $candidate = $this->store->candidateForSlot($slot->id, $targetUid);
            if ($candidate?->source === 'regular') {
                if ($targetUid !== $currentUid) throw new \DomainException('Feste Schichten werden durch die betroffene Assistenz oder über die Konfliktlösung geändert.');
                if (!$this->fixedShifts->deleteOwnOccurrence($team, $slot, $targetUid)) throw new \DomainException('Die feste Schicht wurde zwischenzeitlich geändert. Bitte neu laden.');
                return;
            }
            $this->store->removeCandidate($slot->id, $targetUid);
        });
    }

    public function reportFixedConflict(Team $team, string $month, int $slotId, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        $this->withMutableMonth($team->code,$month,$currentUid,function() use($team,$month,$slotId,$currentUid): void {
            $this->fixedShifts->reportConflict($team,$month,$this->requireSlot($slotId,$team->code,$month),$currentUid);
        });
    }

    public function resolveFixedConflict(Team $team, string $month, int $slotId, string $keptUid, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        $this->withMutableMonth($team->code,$month,$currentUid,function() use($team,$month,$slotId,$keptUid,$currentUid): void {
            $this->fixedShifts->resolveConflict($team,$month,$this->requireSlot($slotId,$team->code,$month),$keptUid,$currentUid);
        });
    }

    public function updateCandidateMetadata(Team $team, string $month, int $slotId, string $preference, string $note, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        $this->assertAssignableAssistantInTeam($team, $currentUid);
        $preference = strtolower(trim($preference));
        if (!in_array($preference, ['neutral', 'favorite', 'emergency'], true)) {
            throw new \InvalidArgumentException('Unbekannte Schichtpräferenz.');
        }
        $note = trim($note);
        $length = preg_match_all('/./us', $note);
        if ($length === false) {
            throw new \InvalidArgumentException('Schichtanmerkungen müssen gültiges UTF-8 enthalten.');
        }
        if ($length > self::MAX_CANDIDATE_NOTE_LENGTH) {
            throw new \InvalidArgumentException('Schichtanmerkungen dürfen höchstens 500 Zeichen lang sein.');
        }

        $this->withMutableMonth($team->code, $month, $currentUid, function () use ($slotId, $team, $month, $currentUid, $preference, $note): void {
            $slot = $this->requireSlot($slotId, $team->code, $month);
            if ($this->store->candidateForSlot($slot->id, $currentUid) === null) {
                throw new \DomainException('Nur der eigene vorhandene Schichtwunsch darf gekennzeichnet werden.');
            }
            if (!$this->store->updateCandidateMetadata($slot->id, $currentUid, $preference, $note)) {
                throw new \DomainException('Der Schichtwunsch wurde zwischenzeitlich geändert. Bitte neu laden.');
            }
        });
    }

    public function saveDayNote(Team $team, string $month, string $workDate, string $note, string $currentUid): void {
        if (!$team->isEb) {
            throw new \DomainException('Nur die Einsatzbegleitung darf Bemerkungen bearbeiten.');
        }

        $month = $this->shiftConfig->normalizeMonth($month);
        $workDate = $this->shiftConfig->normalizeDate($workDate);
        if (!str_starts_with($workDate, $month . '-')) {
            throw new \DomainException('Das Datum gehört nicht zum ausgewählten Planungsmonat.');
        }
        $note = $this->normalizeDayNote($note);
        $this->withMutableMonth($team->code, $month, $currentUid, function () use ($team, $workDate, $note, $currentUid): void {
            if ($note === '') {
                $this->store->deleteDayNote($team->code, $workDate);
                return;
            }

            $this->store->saveDayNote($team->code, $workDate, $note, $currentUid);
        });
    }

    public function transitionMonthStatus(Team $team, string $month, string $targetStatus, string $currentUid): string {
        if (!$team->isEb) {
            throw new \DomainException('Nur die Einsatzbegleitung darf den Planstatus ändern.');
        }

        $month = $this->shiftConfig->normalizeMonth($month);
        $targetStatus = strtolower(trim($targetStatus));
        $currentStatus = $this->store->monthStatus($team->code, $month);
        $allowedTargets = [
            self::STATUS_DRAFT => [self::STATUS_PLANNED],
            self::STATUS_PLANNED => [self::STATUS_DRAFT, self::STATUS_APPROVED],
            self::STATUS_APPROVED => [self::STATUS_PLANNED],
        ];
        if (!in_array($targetStatus, $allowedTargets[$currentStatus] ?? [], true)) {
            throw new \DomainException('Dieser Planstatuswechsel ist nicht erlaubt.');
        }

        $this->store->ensureMonthStatus($team->code, $month, $currentUid);
        $unavailable = $targetStatus === self::STATUS_APPROVED
            ? $this->planningHints->contextForMonth(
                $month,
                array_keys($team->assignableAssistantUidMap()),
                array_values(array_filter($this->shiftConfig->segments($team->settings), static fn(array $segment): bool => $segment['enabled']))
            )['unavailable']
            : [];
        return $this->store->transactional(function () use ($team, $month, $targetStatus, $currentUid, $currentStatus, $unavailable): string {
            $lockedStatus = $this->store->lockMonthStatus($team->code, $month, [$currentStatus]);
            if ($lockedStatus !== $currentStatus) {
                throw new \DomainException('Der Planstatus wurde zwischenzeitlich geändert. Bitte neu laden.');
            }
            if ($targetStatus === self::STATUS_APPROVED) {
                $this->ensureMonthSlots($team, $month);
                $slots = array_values(array_filter($this->store->slotsForMonth($team->code,$month), static fn(ShiftSlot $slot): bool => $slot->enabled));
                $this->fixedShifts->materializeMonth($team,$slots,$unavailable);
                if ($this->fixedShifts->conflictsForSlots($team,array_map(static fn(ShiftSlot $slot): int => $slot->id,$slots),$currentUid) !== []) {
                    throw new \DomainException('Offene Festschichtkonflikte müssen vor der Genehmigung gelöst werden.');
                }
            }
            if (!$this->store->transitionMonthStatus($team->code, $month, $currentStatus, $targetStatus, $currentUid)) {
                throw new \DomainException('Der Planstatus wurde zwischenzeitlich geändert. Bitte neu laden.');
            }

            return $targetStatus;
        });
    }

    private function ensureMonthSlots(Team $team, string $month): void {
        $existingSlots = $this->store->slotsForMonth($team->code, $month);
        $existing = [];
        foreach ($existingSlots as $slot) {
            $existing[$slot->workDate . '|' . $slot->segmentKey] = $slot;
        }

        $segments = $this->shiftConfig->segments($team->settings);
        $segmentKeys = array_flip(array_map(static fn(array $segment): string => (string)$segment['key'], $segments));
        foreach ($existingSlots as $slot) {
            if (isset($segmentKeys[$slot->segmentKey])) {
                continue;
            }

            $this->store->updateSlotDefinition(
                $slot->id,
                $slot->label,
                $slot->startsAt,
                $slot->endsAt,
                false
            );
        }

        foreach ($this->shiftConfig->monthDays($month) as $day) {
            foreach ($segments as $segment) {
                $key = $day['date'] . '|' . $segment['key'];
                if (isset($existing[$key])) {
                    $this->store->updateSlotDefinition(
                        $existing[$key]->id,
                        (string)$segment['label'],
                        (string)$segment['startsAt'],
                        (string)$segment['endsAt'],
                        (bool)$segment['enabled']
                    );
                    continue;
                }

                if (!$segment['enabled']) {
                    continue;
                }

                $this->store->insertSlot(
                    $team->code,
                    $month,
                    (string)$day['date'],
                    (string)$segment['key'],
                    (string)$segment['label'],
                    (string)$segment['startsAt'],
                    (string)$segment['endsAt'],
                    true
                );
            }
        }
    }

    /** @param list<ShiftSlot> $slots */
    private function segmentsFromFrozenSlots(array $slots): array {
        $segments = [];
        foreach ($slots as $slot) {
            if (isset($segments[$slot->segmentKey])) {
                continue;
            }
            $segments[$slot->segmentKey] = [
                'key' => $slot->segmentKey,
                'label' => $slot->label,
                'startsAt' => $slot->startsAt,
                'endsAt' => $slot->endsAt,
                'enabled' => true,
            ];
        }

        return array_values($segments);
    }

    private function requireSlot(int $slotId, string $teamCode, string $month): ShiftSlot {
        $slot = $this->store->slotForMonth($slotId, $teamCode, $month);
        if ($slot === null || !$slot->enabled) {
            throw new \DomainException('Diese Schicht wurde nicht gefunden.');
        }

        return $slot;
    }

    private function candidatePayload(array $candidates, array $assistantLabels, string $currentUid, string $workDate, string $segmentKey, array $workloadByUid, array $unavailable): array {
        return array_map(function (ShiftCandidate $candidate) use ($assistantLabels, $currentUid, $workDate, $segmentKey, $workloadByUid, $unavailable): array {
            $payload = $candidate->toArray($assistantLabels, $currentUid);
            $entry = $workloadByUid[$candidate->assistantUid] ?? [];
            $weekKey = (new \DateTimeImmutable($workDate))->format('o-W');
            $week = null;
            foreach ($entry['weeks'] ?? [] as $item) {
                if (($item['key'] ?? '') === $weekKey) {
                    $week = $item;
                }
            }
            $statuses = [$entry['monthStatus'] ?? 'normal', $week['status'] ?? 'normal'];
            $payload['workloadStatus'] = in_array('under', $statuses, true) ? 'under' : (in_array('over', $statuses, true) ? 'over' : 'normal');
            $payload['unavailable'] = isset($unavailable[$workDate.'|'.$segmentKey][$candidate->assistantUid]);
            return $payload;
        }, $candidates);
    }

    private function assertCandidateMutationAllowed(Team $team, string $targetUid, string $currentUid): void {
        if ($targetUid === $currentUid) {
            return;
        }

        if ($team->isEb) {
            return;
        }

        throw new \DomainException('Assistenzkräfte dürfen nur eigene Einträge bearbeiten.');
    }

    private function assertAssignableAssistantInTeam(Team $team, string $assistantUid): void {
        $assistant = $team->assistantByUid($assistantUid);
        if ($assistant === null) {
            throw new \DomainException('Diese Assistenz gehört nicht zum Team.');
        }

        if (!$assistant->canReceiveShifts) {
            throw new \DomainException('Einsatzbegleitungen können keiner Schicht zugeteilt werden.');
        }
    }

    private function normalizeDayNote(string $note): string {
        $note = trim($note);
        $length = preg_match_all('/./us', $note);
        if ($length === false) {
            throw new \InvalidArgumentException('Bemerkungen müssen gültiges UTF-8 enthalten.');
        }
        if ($length > self::MAX_DAY_NOTE_LENGTH) {
            throw new \InvalidArgumentException('Bemerkungen dürfen höchstens 2.000 Zeichen lang sein.');
        }

        return $note;
    }

    private function withMutableMonth(string $teamCode, string $month, string $currentUid, callable $operation): mixed {
        $this->store->ensureMonthStatus($teamCode, $month, $currentUid);

        return $this->store->transactional(function () use ($teamCode, $month, $operation): mixed {
            $status = $this->store->lockMonthStatus(
                $teamCode,
                $month,
                [self::STATUS_DRAFT, self::STATUS_PLANNED]
            );
            if ($status === null) {
                throw new \DomainException('Der genehmigte Monatsplan ist gegen Änderungen gesperrt.');
            }

            return $operation();
        });
    }
}

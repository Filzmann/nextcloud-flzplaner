<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Store\ShiftPlanStore;

final class FixedShiftService {
    public function __construct(private ShiftPlanStore $store, private ShiftConfigService $shiftConfig) {}

    public function personal(Team $team, string $uid): array {
        return array_values(array_map(
            static fn(array $rule): array => ['weekday'=>(int)$rule['weekday'], 'segmentKey'=>(string)$rule['segmentKey']],
            array_filter($this->store->regularShiftRulesForTeam($team->code), static fn(array $rule): bool => ($rule['userUid'] ?? '') === $uid)
        ));
    }

    public function savePersonal(Team $team, string $uid, array $rules): array {
        $assistant = $team->assistantByUid($uid);
        if ($assistant === null || !$assistant->canReceiveShifts) throw new \DomainException('Nur schichtfähige Assistenzkräfte dürfen regelmäßige Schichten speichern.');
        $segments = [];
        foreach ($this->shiftConfig->segments($team->settings) as $segment) if ($segment['enabled']) $segments[(string)$segment['key']] = true;
        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) throw new \InvalidArgumentException('Regelmäßige Schichten sind ungültig.');
            $weekday = (int)($rule['weekday'] ?? 0);
            $segmentKey = trim((string)($rule['segmentKey'] ?? ''));
            if ($weekday < 1 || $weekday > 7 || !isset($segments[$segmentKey])) throw new \InvalidArgumentException('Wochentag oder Schichtart ist ungültig.');
            $normalized[$weekday.'|'.$segmentKey] = compact('weekday','segmentKey');
        }
        $normalized = array_values($normalized);
        usort($normalized, static fn(array $a,array $b): int => [$a['weekday'],$a['segmentKey']] <=> [$b['weekday'],$b['segmentKey']]);
        $this->store->replaceRegularShiftRules($team->code, $uid, $normalized);
        return $normalized;
    }

    /** @param list<ShiftSlot> $slots */
    public function materializeMonth(Team $team, array $slots): void {
        $rules = [];
        $assignable = $team->assignableAssistantUidMap();
        foreach ($this->store->regularShiftRulesForTeam($team->code) as $rule) {
            $uid = (string)($rule['userUid'] ?? '');
            if (!isset($assignable[$uid])) continue;
            $rules[(int)$rule['weekday'].'|'.(string)$rule['segmentKey']][] = $uid;
        }
        $desired=[];
        foreach ($slots as $slot) {
            if (!$slot->enabled) continue;
            $weekday = (int)(new \DateTimeImmutable($slot->workDate))->format('N');
            foreach ($rules[$weekday.'|'.$slot->segmentKey] ?? [] as $uid) {
                $desired[$slot->id.'|'.$uid]=true;
                $this->store->materializeFixedCandidate($slot->id, $uid);
            }
        }
        $slotIds=array_map(static fn(ShiftSlot $slot): int=>$slot->id,$slots);
        foreach ($this->store->candidatesForSlotIds($slotIds) as $slotId=>$candidates) {
            foreach ($candidates as $candidate) {
                if ($candidate->source==='regular' && !isset($desired[$slotId.'|'.$candidate->assistantUid])) $this->store->removeCandidate((int)$slotId,$candidate->assistantUid);
            }
        }
    }

    public function deleteOwnOccurrence(Team $team, ShiftSlot $slot, string $uid): bool {
        $assistant = $team->assistantByUid($uid);
        if ($slot->teamCode !== $team->code || $assistant === null || !$assistant->canReceiveShifts) throw new \DomainException('Diese feste Schicht darf nicht gelöscht werden.');
        return $this->store->markFixedCandidateDeleted($slot->id, $uid);
    }

    /** @param list<int> $slotIds */
    public function conflictsForSlots(Team $team, array $slotIds, string $currentUid): array {
        $candidates = $this->store->candidatesForSlotIds($slotIds);
        $reports = $this->store->fixedConflictReports($slotIds);
        $result = [];
        foreach ($candidates as $slotId => $items) {
            $fixed = array_values(array_filter($items, static fn(ShiftCandidate $candidate): bool => $candidate->source === 'regular' && !$candidate->fixedDeleted));
            if (count($fixed) < 2) continue;
            $uids = array_values(array_map(static fn(ShiftCandidate $candidate): string => $candidate->assistantUid, $fixed));
            $report = $reports[(int)$slotId] ?? [];
            $result[(int)$slotId] = [
                'status'=>(string)($report['status'] ?? 'detected'),
                'candidateUids'=>$uids,
                'canReport'=>in_array($currentUid,$uids,true) && ($report['status'] ?? '') !== 'escalated',
                'canResolve'=>$team->isEb && ($report['status'] ?? '') === 'escalated',
            ];
        }
        return $result;
    }

    public function reportConflict(Team $team, string $month, ShiftSlot $slot, string $uid): void {
        if ($slot->teamCode !== $team->code || $slot->planMonth !== $this->shiftConfig->normalizeMonth($month)) throw new \DomainException('Diese Schicht gehört nicht zum Plan.');
        $conflict = $this->conflictsForSlots($team, [$slot->id], $uid)[$slot->id] ?? null;
        if ($conflict === null || !in_array($uid,$conflict['candidateUids'],true)) throw new \DomainException('Nur Beteiligte dürfen diesen Konflikt weitergeben.');
        $this->store->reportFixedConflict($slot->id,$uid);
    }

    public function resolveConflict(Team $team, string $month, ShiftSlot $slot, string $keptUid, string $uid): void {
        if (!$team->isEb) throw new \DomainException('Nur die Einsatzbegleitung darf Festschichtkonflikte lösen.');
        if ($slot->teamCode !== $team->code || $slot->planMonth !== $this->shiftConfig->normalizeMonth($month)) throw new \DomainException('Diese Schicht gehört nicht zum Plan.');
        $conflict = $this->conflictsForSlots($team, [$slot->id], $uid)[$slot->id] ?? null;
        if ($conflict === null || !in_array($keptUid,$conflict['candidateUids'],true)) throw new \DomainException('Die beizubehaltende Festschicht ist ungültig.');
        $this->store->resolveFixedConflict($slot->id,$keptUid,$uid);
    }
}

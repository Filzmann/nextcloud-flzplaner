<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use DateTimeImmutable;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Store\ShiftPlanStore;

final class WorkloadPreferenceService {
    private const MAX_LIMIT = 100;

    public function __construct(private ShiftPlanStore $store) {
    }

    public function personal(Team $team, string $uid): array {
        return $this->store->workloadLimitsForTeam($team->code)[$uid] ?? $this->emptyLimits();
    }

    public function savePersonal(Team $team, string $uid, mixed $weeklyMin, mixed $weeklyMax, mixed $monthlyMin, mixed $monthlyMax): array {
        $assistant = $team->assistantByUid($uid);
        if ($assistant === null || !$assistant->canReceiveShifts) {
            throw new \DomainException('Nur schichtfähige Teammitglieder dürfen persönliche Schichtgrenzen speichern.');
        }
        $limits = [
            'weeklyMin' => $this->normalizeLimit($weeklyMin, 'Wöchentliches Minimum'),
            'weeklyMax' => $this->normalizeLimit($weeklyMax, 'Wöchentliches Maximum'),
            'monthlyMin' => $this->normalizeLimit($monthlyMin, 'Monatliches Minimum'),
            'monthlyMax' => $this->normalizeLimit($monthlyMax, 'Monatliches Maximum'),
        ];
        $this->assertRange($limits['weeklyMin'], $limits['weeklyMax'], 'Woche');
        $this->assertRange($limits['monthlyMin'], $limits['monthlyMax'], 'Monat');
        $this->store->saveWorkloadLimits($team->code, $uid, $limits);

        return $limits;
    }

    public function overview(Team $team, string $month, string $currentUid): array {
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        if ($first === false || $first->format('Y-m') !== $month) {
            throw new \InvalidArgumentException('Ungültiger Planungsmonat.');
        }
        $last = $first->modify('last day of this month');
        $rangeStart = $first->modify('monday this week');
        $rangeEnd = $last->modify('sunday this week');
        $limitsByUid = $this->store->workloadLimitsForTeam($team->code);
        $monthCounts = [];
        $weekCounts = [];
        $occurrences = [];
        foreach ($this->store->candidateDates($team->code, $rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')) as $index => $row) {
            $uid = (string)($row['assistant_uid'] ?? '');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($row['work_date'] ?? ''));
            if ($uid === '' || $date === false) {
                continue;
            }
            $segmentKey = (string)($row['segment_key'] ?? ('manual-'.$index));
            $occurrences[$uid.'|'.$date->format('Y-m-d').'|'.$segmentKey] = true;
            if ((bool)($row['fixed_deleted'] ?? false)) continue;
            $weekKey = $date->format('o-W');
            $weekCounts[$uid][$weekKey] = ($weekCounts[$uid][$weekKey] ?? 0) + 1;
            if ($date->format('Y-m') === $month) {
                $monthCounts[$uid] = ($monthCounts[$uid] ?? 0) + 1;
            }
        }
        $rulesByDay = [];
        foreach ($this->store->regularShiftRulesForTeam($team->code) as $rule) {
            $rulesByDay[(int)($rule['weekday'] ?? 0)][] = $rule;
        }
        for ($date = $rangeStart; $date <= $rangeEnd; $date = $date->modify('+1 day')) {
            foreach ($rulesByDay[(int)$date->format('N')] ?? [] as $rule) {
                $uid = (string)($rule['userUid'] ?? '');
                $segmentKey = (string)($rule['segmentKey'] ?? '');
                $key = $uid.'|'.$date->format('Y-m-d').'|'.$segmentKey;
                if ($uid === '' || $segmentKey === '' || isset($occurrences[$key])) continue;
                $occurrences[$key] = true;
                $weekKey = $date->format('o-W');
                $weekCounts[$uid][$weekKey] = ($weekCounts[$uid][$weekKey] ?? 0) + 1;
                if ($date->format('Y-m') === $month) $monthCounts[$uid] = ($monthCounts[$uid] ?? 0) + 1;
            }
        }
        $weekKeys = [];
        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $weekKeys[$day->format('o-W')] = 'KW ' . $day->format('W');
        }
        $overview = [];
        foreach ($team->assistants() as $assistant) {
            if (!$assistant->canReceiveShifts || (!$team->isEb && $assistant->uid !== $currentUid)) {
                continue;
            }
            $limits = $limitsByUid[$assistant->uid] ?? $this->emptyLimits();
            $weeks = [];
            foreach ($weekKeys as $key => $label) {
                $count = $weekCounts[$assistant->uid][$key] ?? 0;
                $weeks[] = [
                    'key' => $key,
                    'label' => $label,
                    'count' => $count,
                    'status' => $this->status($count, $limits['weeklyMin'], $limits['weeklyMax']),
                ];
            }
            $monthCount = $monthCounts[$assistant->uid] ?? 0;
            $monthStatus = $this->status($monthCount, $limits['monthlyMin'], $limits['monthlyMax']);
            $statuses = [$monthStatus, ...array_column($weeks, 'status')];
            $overallStatus = in_array('under', $statuses, true) ? 'under' : (in_array('over', $statuses, true) ? 'over' : 'normal');
            $overview[] = [
                'uid' => $assistant->uid,
                'displayName' => $assistant->displayName,
                ...$limits,
                'monthCount' => $monthCount,
                'monthStatus' => $monthStatus,
                'status' => $overallStatus,
                'weeks' => $weeks,
            ];
        }

        return $overview;
    }

    private function normalizeLimit(mixed $value, string $label): ?int {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException($label . ' muss eine ganze Zahl sein.');
        }
        $value = (int)$value;
        if ($value < 0 || $value > self::MAX_LIMIT) {
            throw new \InvalidArgumentException($label . ' muss zwischen 0 und 100 liegen.');
        }
        return $value;
    }

    private function assertRange(?int $minimum, ?int $maximum, string $period): void {
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new \InvalidArgumentException("Das Minimum pro {$period} darf nicht über dem Maximum liegen.");
        }
    }

    private function status(int $count, ?int $minimum, ?int $maximum): string {
        if ($minimum !== null && $count < $minimum) {
            return 'under';
        }
        if ($maximum !== null && $count > $maximum) {
            return 'over';
        }
        return 'normal';
    }

    private function emptyLimits(): array {
        return [
            'weeklyMin' => null,
            'weeklyMax' => null,
            'monthlyMin' => null,
            'monthlyMax' => null,
        ];
    }
}

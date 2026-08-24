<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\LocalBase\Calendar\AbsenceQueryEvent;
use OCA\LocalBase\Calendar\ScheduleConflictQueryEvent;
use OCP\EventDispatcher\IEventDispatcher;

/** Aggregiert optionale Abwesenheiten und Kalenderbelegungen ohne Schreibwirkung oder fremde Detaildaten. */
class PlanningHintService {
    public function __construct(
        private IEventDispatcher $events,
        private AdPlanerLogger $logger,
    ) {}

    /** @param list<string> $employeeUids
     *  @return array<string, list<array{employeeUid:string,type:string,marker:string,label:string,blocks:bool}>>
     */
    public function forMonth(string $month, array $employeeUids): array {
        if (preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) !== 1
            || !checkdate((int)$matches[2], 1, (int)$matches[1])) {
            throw new \InvalidArgumentException('Ungültiger Planungsmonat.');
        }
        $employeeUids = array_values(array_unique(array_filter(array_map('strval', $employeeUids))));
        if ($employeeUids === []) {
            return [];
        }

        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable($month . '-01 00:00:00', $utc);
        $end = $start->modify('+1 month');
        $hints = [];

        try {
            $absenceEvent = new AbsenceQueryEvent($start, $end, $employeeUids);
            $this->events->dispatchTyped($absenceEvent);
            foreach ($absenceEvent->absences() as $absence) {
                $payload = $absence->toArray();
                $this->appendForDays(
                    $hints,
                    new DateTimeImmutable($payload['start']),
                    new DateTimeImmutable($payload['end']),
                    $start,
                    $end,
                    [
                        'employeeUid' => $payload['employeeUid'],
                        'type' => 'absence',
                        'marker' => $payload['marker'],
                        'label' => 'Urlaub',
                        'blocks' => false,
                    ]
                );
            }
        } catch (\Throwable $error) {
            $this->logger->error('planning_hint_absences', $error, ['month' => $month]);
        }

        foreach ($employeeUids as $employeeUid) {
            try {
                $conflictEvent = new ScheduleConflictQueryEvent($employeeUid, $start, $end);
                $this->events->dispatchTyped($conflictEvent);
                foreach ($conflictEvent->conflicts() as $conflict) {
                    $payload = $conflict->toArray();
                    $this->appendForDays(
                        $hints,
                        new DateTimeImmutable($payload['start']),
                        new DateTimeImmutable($payload['end']),
                        $start,
                        $end,
                        [
                            'employeeUid' => $employeeUid,
                            'type' => 'calendar',
                            'marker' => 'K',
                            'label' => $payload['type'] === 'shift' ? 'Dienst' : 'Termin',
                            'blocks' => false,
                        ]
                    );
                }
            } catch (\Throwable $error) {
                $this->logger->error('planning_hint_calendar', $error, ['month' => $month]);
            }
        }

        foreach ($hints as &$dayHints) {
            usort($dayHints, static fn(array $left, array $right): int => [$left['employeeUid'], $left['marker']] <=> [$right['employeeUid'], $right['marker']]);
        }
        unset($dayHints);

        return $hints;
    }

    private function appendForDays(array &$hints, DateTimeImmutable $hintStart, DateTimeImmutable $hintEnd, DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd, array $hint): void {
        $cursor = $hintStart < $monthStart ? $monthStart : $hintStart;
        $limit = $hintEnd > $monthEnd ? $monthEnd : $hintEnd;
        $cursor = $cursor->setTime(0, 0);
        while ($cursor < $limit) {
            $hints[$cursor->format('Y-m-d')][] = $hint;
            $cursor = $cursor->modify('+1 day');
        }
    }
}

<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\FlzPlaner\Model\ShiftSlot;
use OCA\LocalBase\Calendar\AbsenceInterval;
use OCA\LocalBase\Calendar\AbsenceQueryEvent;
use OCA\LocalBase\Calendar\ScheduleConflictQueryEvent;
use OCP\EventDispatcher\IEventDispatcher;

/** Aggregiert optionale Abwesenheiten und Kalenderbelegungen ohne Schreibwirkung oder fremde Detaildaten. */
class PlanningHintService {
    public function __construct(
        private IEventDispatcher $events,
        private FlzPlanerLogger $logger,
    ) {}

    /** @param list<string> $employeeUids
     *  @return array<string, list<array{employeeUid:string,type:string,marker:string,label:string,blocks:bool}>>
     */
    public function forMonth(string $month, array $employeeUids): array {
        return $this->contextForMonth($month, $employeeUids, [])['hints'];
    }

    /** @param list<string> $employeeUids
     *  @param list<array{key:string,startsAt:string,endsAt:string,enabled?:bool}> $segments
     *  @return array{hints:array<string,list<array{employeeUid:string,type:string,marker:string,label:string,blocks:bool}>>,unavailable:array<string,array<string,true>>}
     */
    public function contextForMonth(string $month, array $employeeUids, array $segments): array {
        if (preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) !== 1
            || !checkdate((int)$matches[2], 1, (int)$matches[1])) {
            throw new \InvalidArgumentException('Ungültiger Planungsmonat.');
        }
        $employeeUids = array_values(array_unique(array_filter(array_map('strval', $employeeUids))));
        if ($employeeUids === []) {
            return ['hints' => [], 'unavailable' => []];
        }

        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable($month . '-01 00:00:00', $utc);
        $end = $start->modify('+1 month');
        $hints = [];
        $unavailable = [];

        try {
            $absenceEvent = new AbsenceQueryEvent($start, $segments === [] ? $end : $end->modify('+1 day'), $employeeUids);
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
                        'blocks' => true,
                    ]
                );
                $this->appendUnavailableSlots($unavailable, $absence, $start, $end, $segments);
            }
        } catch (\Throwable $error) {
            $this->logger->error('planning_hint_absences', $error, ['month' => $month]);
        }

        foreach ($employeeUids as $employeeUid) {
            try {
                $conflictEvent = new ScheduleConflictQueryEvent($employeeUid, $start, $end, 'flzplaner');
                $this->events->dispatchTyped($conflictEvent);
                foreach ($conflictEvent->conflicts() as $conflict) {
                    $payload = $conflict->toArray();
                    $isShift = $payload['type'] === 'shift';
                    $this->appendForDays(
                        $hints,
                        new DateTimeImmutable($payload['start']),
                        new DateTimeImmutable($payload['end']),
                        $start,
                        $end,
                        [
                            'employeeUid' => $employeeUid,
                            'type' => 'calendar',
                            'marker' => $isShift ? 'Dienst/Büro' : 'K',
                            'label' => $isShift ? 'Dienst/Büro' : 'Termin',
                            'blocks' => $isShift,
                        ]
                    );
                    if ($isShift) {
                        $this->appendUnavailableInterval(
                            $unavailable,
                            $employeeUid,
                            new DateTimeImmutable($payload['start']),
                            new DateTimeImmutable($payload['end']),
                            $start,
                            $end,
                            $segments,
                            'calendar',
                        );
                    }
                }
            } catch (\Throwable $error) {
                $this->logger->error('planning_hint_calendar', $error, ['month' => $month]);
            }
        }

        foreach ($hints as &$dayHints) {
            usort($dayHints, static fn(array $left, array $right): int => [$left['employeeUid'], $left['marker']] <=> [$right['employeeUid'], $right['marker']]);
        }
        unset($dayHints);

        return ['hints' => $hints, 'unavailable' => $unavailable];
    }

    public function assertAvailableForSlot(ShiftSlot $slot, string $employeeUid): void {
        [$start, $end] = $this->slotInterval($slot->workDate, $slot->startsAt, $slot->endsAt, new DateTimeZone('UTC'));
        try {
            $event = new AbsenceQueryEvent($start, $end, [$employeeUid]);
            $this->events->dispatchTyped($event);
            foreach ($event->absences() as $absence) {
                if ($absence->employeeUid() === $employeeUid && $absence->overlaps($start, $end)) {
                    throw new \DomainException('Urlaub blockiert diese Schicht.');
                }
            }
        } catch (\DomainException $error) {
            throw $error;
        } catch (\Throwable $error) {
            $this->logger->error('planning_hint_absence_availability', $error, ['month' => $slot->planMonth]);
        }

        try {
            $event = new ScheduleConflictQueryEvent($employeeUid, $start, $end, 'flzplaner');
            $this->events->dispatchTyped($event);
            foreach ($event->conflicts() as $conflict) {
                if ($conflict->type() === 'shift' && $conflict->start() < $end && $conflict->end() > $start) {
                    throw new \DomainException('Dienst/Büro blockiert diese Assistenzschicht.');
                }
            }
        } catch (\DomainException $error) {
            throw $error;
        } catch (\Throwable $error) {
            $this->logger->error('planning_hint_calendar_availability', $error, ['month' => $slot->planMonth]);
            throw new \DomainException('Die Dienstkonfliktprüfung ist derzeit nicht möglich.', 0, $error);
        }
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

    /** @param array<string,array<string,true>> $unavailable */
    private function appendUnavailableSlots(array &$unavailable, AbsenceInterval $absence, DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd, array $segments): void {
        $this->appendUnavailableInterval(
            $unavailable,
            $absence->employeeUid(),
            $absence->start(),
            $absence->end(),
            $monthStart,
            $monthEnd,
            $segments,
            'vacation',
        );
    }

    private function appendUnavailableInterval(array &$unavailable, string $employeeUid, DateTimeImmutable $intervalStart, DateTimeImmutable $intervalEnd, DateTimeImmutable $monthStart, DateTimeImmutable $monthEnd, array $segments, string $reason): void {
        for ($day = $monthStart; $day < $monthEnd; $day = $day->modify('+1 day')) {
            foreach ($segments as $segment) {
                if (($segment['enabled'] ?? true) !== true || trim((string)($segment['key'] ?? '')) === '') continue;
                [$slotStart, $slotEnd] = $this->slotInterval(
                    $day->format('Y-m-d'),
                    (string)($segment['startsAt'] ?? ''),
                    (string)($segment['endsAt'] ?? ''),
                    $monthStart->getTimezone()
                );
                if ($intervalStart < $slotEnd && $intervalEnd > $slotStart) {
                    $unavailable[$day->format('Y-m-d') . '|' . $segment['key']][$employeeUid] = $reason;
                }
            }
        }
    }

    /** @return array{DateTimeImmutable,DateTimeImmutable} */
    private function slotInterval(string $date, string $startsAt, string $endsAt, DateTimeZone $timezone): array {
        $start = new DateTimeImmutable($date . ' ' . $startsAt, $timezone);
        $end = new DateTimeImmutable($date . ' ' . $endsAt, $timezone);
        if ($end <= $start) $end = $end->modify('+1 day');
        return [$start, $end];
    }
}

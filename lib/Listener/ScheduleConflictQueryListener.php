<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Listener;

use DateTimeImmutable;
use DateTimeZone;
use OCA\AdPlaner\Repository\ShiftPlanRepository;
use OCA\LocalBase\Calendar\ScheduleConflict;
use OCA\LocalBase\Calendar\ScheduleConflictQueryEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** Veröffentlicht aktive Assistenzzuweisungen read-only über die öffentliche Konflikt-API. */
final class ScheduleConflictQueryListener implements IEventListener {
    public function __construct(private ShiftPlanRepository $plans) {}

    public function handle(Event $event): void {
        if (!$event instanceof ScheduleConflictQueryEvent) return;

        $timezone = new DateTimeZone('UTC');
        foreach ($this->plans->candidateIntervalsForEmployee($event->employeeUid(), $event->start(), $event->end()) as $row) {
            if ((bool)($row['fixed_deleted'] ?? false)) continue;
            $start = new DateTimeImmutable((string)$row['work_date'] . ' ' . (string)$row['starts_at'], $timezone);
            $end = new DateTimeImmutable((string)$row['work_date'] . ' ' . (string)$row['ends_at'], $timezone);
            if ($end <= $start) $end = $end->modify('+1 day');
            if ($start >= $event->end() || $end <= $event->start()) continue;

            $event->add(new ScheduleConflict('shift', $start, $end, 'Assistenz', 'adplaner'));
        }
    }
}

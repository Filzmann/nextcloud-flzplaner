<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) {
        class Event { public function __construct() {} }
    }
    if (!interface_exists(IEventDispatcher::class)) {
        interface IEventDispatcher {
            public function dispatchTyped(Event $event): Event;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Service\PlanningHintService;
    use OCA\AdPlaner\Service\AdPlanerLogger;
    use OCA\LocalBase\Calendar\AbsenceInterval;
    use OCA\LocalBase\Calendar\AbsenceQueryEvent;
    use OCA\LocalBase\Calendar\ScheduleConflict;
    use OCA\LocalBase\Calendar\ScheduleConflictQueryEvent;
    use OCP\EventDispatcher\Event;
    use OCP\EventDispatcher\IEventDispatcher;
    use function OCA\AdPlaner\Tests\assertSameValue;

    final class PlanningHintEventDispatcherFake implements IEventDispatcher {
        public bool $provideData = true;
        public bool $throwAbsence = false;
        public bool $throwCalendar = false;

        public function dispatchTyped(Event $event): Event {
            if ($this->throwAbsence && $event instanceof AbsenceQueryEvent) {
                throw new \RuntimeException('Absence provider failed');
            }
            if ($this->throwCalendar && $event instanceof ScheduleConflictQueryEvent) {
                throw new \RuntimeException('Calendar provider failed');
            }
            if (!$this->provideData) {
                return $event;
            }
            $utc = new \DateTimeZone('UTC');
            if ($event instanceof AbsenceQueryEvent) {
                $event->add(new AbsenceInterval('assistant-a', new \DateTimeImmutable('2026-08-02', $utc), new \DateTimeImmutable('2026-08-04', $utc), 'planned'));
                $event->add(new AbsenceInterval('assistant-b', new \DateTimeImmutable('2026-08-05', $utc), new \DateTimeImmutable('2026-08-06', $utc), 'approved'));
                $event->add(new AbsenceInterval('foreign-person', new \DateTimeImmutable('2026-08-09', $utc), new \DateTimeImmutable('2026-08-10', $utc), 'approved'));
            }
            if ($event instanceof ScheduleConflictQueryEvent && $event->employeeUid() === 'assistant-a') {
                $event->add(new ScheduleConflict('appointment', new \DateTimeImmutable('2026-08-07 10:00:00', $utc), new \DateTimeImmutable('2026-08-07 11:00:00', $utc), 'Vertraulicher Titel'));
            }

            return $event;
        }
    }

    final class PlanningHintLoggerFake extends AdPlanerLogger {
        public array $errors = [];

        public function __construct() {}

        public function error(string $action, \Throwable $exception, array $context = []): void {
            $this->errors[] = compact('action', 'context');
        }
    }

    $events = new PlanningHintEventDispatcherFake();
    $logger = new PlanningHintLoggerFake();
    $service = new PlanningHintService($events, $logger);
    $hints = $service->forMonth('2026-08', ['assistant-a', 'assistant-b']);

    assertSameValue('U?', $hints['2026-08-02'][0]['marker'] ?? null, 'Planned vacation is exposed as U?.');
    assertSameValue(false, $hints['2026-08-02'][0]['blocks'] ?? null, 'Planning hints never block AdPlaner mutations.');
    assertSameValue('U', $hints['2026-08-05'][0]['marker'] ?? null, 'Approved vacation remains distinguishable.');
    assertSameValue('K', $hints['2026-08-07'][0]['marker'] ?? null, 'Calendar occupation is exposed as a compact marker.');
    assertSameValue('Termin', $hints['2026-08-07'][0]['label'] ?? null, 'Calendar titles are not leaked into the team plan.');
    assertSameValue('assistant-a', $hints['2026-08-07'][0]['employeeUid'] ?? null, 'Hints remain assigned to the visible team member.');
    assertSameValue(false, isset($hints['2026-08-09']), 'Absences returned for a non-requested person must not enter the team plan.');
    $hintUids = [];
    foreach ($hints as $dayHints) {
        array_push($hintUids, ...array_column($dayHints, 'employeeUid'));
    }
    assertSameValue(false, in_array('foreign-person', $hintUids, true), 'A foreign provider UID must not leak through any day hint.');

    $events->throwAbsence = true;
    $withoutAbsenceProvider = $service->forMonth('2026-08', ['assistant-a']);
    assertSameValue('K', $withoutAbsenceProvider['2026-08-07'][0]['marker'] ?? null, 'A failing absence provider must not block calendar hints.');
    $events->throwAbsence = false;
    $events->throwCalendar = true;
    $withoutCalendarProvider = $service->forMonth('2026-08', ['assistant-a']);
    assertSameValue('U?', $withoutCalendarProvider['2026-08-02'][0]['marker'] ?? null, 'A failing calendar provider must not block absence hints.');
    assertSameValue(
        [
            ['action' => 'planning_hint_absences', 'context' => ['month' => '2026-08']],
            ['action' => 'planning_hint_calendar', 'context' => ['month' => '2026-08']],
        ],
        $logger->errors,
        'Provider failures should be logged without employee identifiers.'
    );
    $events->throwCalendar = false;

    $events->provideData = false;
    assertSameValue([], $service->forMonth('2026-08', ['assistant-a']), 'Missing providers are a valid empty standalone state.');

    echo "AdPlaner planning hint service tests passed\n";
}

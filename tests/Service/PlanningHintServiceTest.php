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

    use OCA\FlzPlaner\Service\PlanningHintService;
    use OCA\FlzPlaner\Service\FlzPlanerLogger;
    use OCA\FlzPlaner\Model\ShiftSlot;
    use OCA\LocalBase\Calendar\AbsenceInterval;
    use OCA\LocalBase\Calendar\AbsenceQueryEvent;
    use OCA\LocalBase\Calendar\ScheduleConflict;
    use OCA\LocalBase\Calendar\ScheduleConflictQueryEvent;
    use OCP\EventDispatcher\Event;
    use OCP\EventDispatcher\IEventDispatcher;
    use function OCA\FlzPlaner\Tests\assertDomainException;
    use function OCA\FlzPlaner\Tests\assertSameValue;

    final class PlanningHintEventDispatcherFake implements IEventDispatcher {
        public bool $provideData = true;
        public bool $throwAbsence = false;
        public bool $throwCalendar = false;
        public array $calendarRequesters = [];

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
                $this->calendarRequesters[] = $event->requesterAppId();
                $event->add(new ScheduleConflict('appointment', new \DateTimeImmutable('2026-08-07 10:00:00', $utc), new \DateTimeImmutable('2026-08-07 11:00:00', $utc), 'Vertraulicher Titel'));
                $event->add(new ScheduleConflict('shift', new \DateTimeImmutable('2026-08-08 10:00:00', $utc), new \DateTimeImmutable('2026-08-08 12:00:00', $utc), 'Dienst/Büro', 'flzcalendar'));
            }

            return $event;
        }
    }

    final class PlanningHintLoggerFake extends FlzPlanerLogger {
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
    assertSameValue(true, $hints['2026-08-02'][0]['blocks'] ?? null, 'Geplanter Urlaub muss Schichten wie im Filzmann Kalender blockieren.');
    assertSameValue('U', $hints['2026-08-05'][0]['marker'] ?? null, 'Approved vacation remains distinguishable.');
    assertSameValue('K', $hints['2026-08-07'][0]['marker'] ?? null, 'Calendar occupation is exposed as a compact marker.');
    assertSameValue('Termin', $hints['2026-08-07'][0]['label'] ?? null, 'Calendar titles are not leaked into the team plan.');
    assertSameValue('Dienst/Büro', $hints['2026-08-08'][0]['marker'] ?? null, 'Ein Calendar-Dienst muss im Planer knapp und sichtbar als Dienst/Büro erscheinen.');
    assertSameValue('Dienst/Büro', $hints['2026-08-08'][0]['label'] ?? null, 'Der sichtbare Diensthinweis darf keinen vertraulichen Kalendertitel übernehmen.');
    assertSameValue('assistant-a', $hints['2026-08-07'][0]['employeeUid'] ?? null, 'Hints remain assigned to the visible team member.');
    assertSameValue(false, isset($hints['2026-08-09']), 'Absences returned for a non-requested person must not enter the team plan.');
    $hintUids = [];
    foreach ($hints as $dayHints) {
        array_push($hintUids, ...array_column($dayHints, 'employeeUid'));
    }
    assertSameValue(false, in_array('foreign-person', $hintUids, true), 'A foreign provider UID must not leak through any day hint.');

    $context = $service->contextForMonth('2026-08', ['assistant-a', 'assistant-b'], [
        ['key'=>'early','startsAt'=>'08:00','endsAt'=>'14:00','enabled'=>true],
        ['key'=>'night','startsAt'=>'20:00','endsAt'=>'08:00','enabled'=>true],
    ]);
    assertSameValue('vacation', $context['unavailable']['2026-08-01|night']['assistant-a'] ?? false, 'Eine Nachtschicht muss Urlaub am Folgetag zeitlich berücksichtigen.');
    assertSameValue('vacation', $context['unavailable']['2026-08-02|early']['assistant-a'] ?? false, 'Geplanter Urlaub muss die betroffene Tagschicht blockieren.');
    assertSameValue('vacation', $context['unavailable']['2026-08-04|night']['assistant-b'] ?? false, 'Eine Nachtschicht vor genehmigtem Urlaub muss als Konflikt gelten.');
    assertSameValue('vacation', $context['unavailable']['2026-08-05|early']['assistant-b'] ?? false, 'Genehmigter Urlaub muss die betroffene Tagschicht blockieren.');
    assertSameValue('calendar', $context['unavailable']['2026-08-08|early']['assistant-a'] ?? false, 'Ein überschneidender Calendar-Dienst muss die Assistenzschicht blockieren.');
    assertSameValue(true, in_array('flzplaner', $events->calendarRequesters, true), 'Der Planer muss sich an der öffentlichen API als anfragende App ausweisen.');
    assertDomainException(
        static fn() => $service->assertAvailableForSlot(new ShiftSlot(1,'A1','2026-08','2026-08-02','early','Früh','08:00','14:00',true), 'assistant-a'),
        'Eine direkte Zuweisung während geplantem Urlaub muss abgewiesen werden.'
    );
    assertDomainException(
        static fn() => $service->assertAvailableForSlot(new ShiftSlot(2,'A1','2026-08','2026-08-08','early','Früh','08:00','14:00',true), 'assistant-a'),
        'Eine direkte Assistenzzuweisung während eines Calendar-Dienstes muss abgewiesen werden.'
    );

    $events->throwAbsence = true;
    $withoutAbsenceProvider = $service->forMonth('2026-08', ['assistant-a']);
    assertSameValue('K', $withoutAbsenceProvider['2026-08-07'][0]['marker'] ?? null, 'A failing absence provider must not block calendar hints.');
    $service->assertAvailableForSlot(new ShiftSlot(2,'A1','2026-08','2026-08-02','early','Früh','08:00','14:00',true), 'assistant-a');
    $events->throwAbsence = false;
    $events->throwCalendar = true;
    $withoutCalendarProvider = $service->forMonth('2026-08', ['assistant-a']);
    assertSameValue('U?', $withoutCalendarProvider['2026-08-02'][0]['marker'] ?? null, 'A failing calendar provider must not block absence hints.');
    assertDomainException(
        static fn() => $service->assertAvailableForSlot(new ShiftSlot(3,'A1','2026-08','2026-08-08','early','Früh','08:00','14:00',true), 'assistant-a'),
        'Eine fehlgeschlagene konkrete Dienstkonfliktprüfung muss die Mutation sicher blockieren.'
    );
    assertSameValue(
        [
            ['action' => 'planning_hint_absences', 'context' => ['month' => '2026-08']],
            ['action' => 'planning_hint_absence_availability', 'context' => ['month' => '2026-08']],
            ['action' => 'planning_hint_calendar', 'context' => ['month' => '2026-08']],
            ['action' => 'planning_hint_calendar_availability', 'context' => ['month' => '2026-08']],
        ],
        $logger->errors,
        'Provider failures should be logged without employee identifiers.'
    );
    $events->throwCalendar = false;

    $events->provideData = false;
    assertSameValue([], $service->forMonth('2026-08', ['assistant-a']), 'Missing providers are a valid empty standalone state.');

    echo "FlzPlaner planning hint service tests passed\n";
}

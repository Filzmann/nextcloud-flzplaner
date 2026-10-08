<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventListener { public function handle(Event $event): void; }
}

namespace OCP {
    interface IDBConnection {}
}

namespace {
    require_once __DIR__ . '/bootstrap.php';

    use OCA\FlzPlaner\Listener\ScheduleConflictQueryListener;
    use OCA\FlzPlaner\Repository\ShiftPlanRepository;
    use OCA\LocalBase\Calendar\ScheduleConflictQueryEvent;
    use function OCA\FlzPlaner\Tests\assertSameValue;

    final class ConflictShiftPlanRepositoryFake extends ShiftPlanRepository {
        public function __construct() {}

        public function candidateIntervalsForEmployee(string $employeeUid, \DateTimeImmutable $start, \DateTimeImmutable $end): array {
            return [
                ['assistant_uid'=>$employeeUid,'work_date'=>'2026-09-07','starts_at'=>'08:00','ends_at'=>'14:00','fixed_deleted'=>false],
                ['assistant_uid'=>$employeeUid,'work_date'=>'2026-09-06','starts_at'=>'20:00','ends_at'=>'08:00','fixed_deleted'=>false],
                ['assistant_uid'=>$employeeUid,'work_date'=>'2026-09-07','starts_at'=>'14:00','ends_at'=>'20:00','fixed_deleted'=>true],
                ['assistant_uid'=>$employeeUid,'work_date'=>'2026-09-07','starts_at'=>'16:00','ends_at'=>'20:00','fixed_deleted'=>false],
            ];
        }
    }

    $start = new \DateTimeImmutable('2026-09-07T07:00:00+00:00');
    $end = new \DateTimeImmutable('2026-09-07T16:00:00+00:00');
    $listener = new ScheduleConflictQueryListener(new ConflictShiftPlanRepositoryFake());
    $event = new ScheduleConflictQueryEvent('assistant-a', $start, $end, 'flzcalendar');
    $listener->handle($event);
    $conflicts = array_map(static fn($conflict): array => $conflict->toArray(), $event->conflicts());

    assertSameValue(2, count($conflicts), 'Tages- und übernächtige Assistenzschichten müssen als echte Überschneidungen gemeldet werden; gelöschte und nur angrenzende nicht.');
    assertSameValue(['Assistenz', 'Assistenz'], array_column($conflicts, 'label'), 'Calendar erhält ausschließlich die knappe, nicht vertrauliche Bezeichnung Assistenz.');
    assertSameValue(['flzplaner', 'flzplaner'], array_column($conflicts, 'sourceAppId'), 'Jeder Konflikt muss seinen öffentlichen Provider ausweisen.');

    $selfQuery = new ScheduleConflictQueryEvent('assistant-a', $start, $end, 'flzplaner');
    $listener->handle($selfQuery);
    assertSameValue([], $selfQuery->conflicts(), 'Die öffentliche API muss Eigenmeldungen des Planers zentral ausfiltern.');

    $application = (string)file_get_contents(__DIR__ . '/../lib/AppInfo/Application.php');
    if (!str_contains($application, 'registerEventListener(ScheduleConflictQueryEvent::class, ScheduleConflictQueryListener::class)')) {
        throw new \RuntimeException('Der Planer-Provider ist nicht am öffentlichen Konflikt-Event registriert.');
    }

    echo "Filzmann Assistenzplanung schedule conflict provider tests passed\n";
}

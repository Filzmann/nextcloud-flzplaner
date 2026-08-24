<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventListener { public function handle(Event $event): void; }
}

namespace OCA\AdPlaner\AppInfo { final class Application { public const APP_ID = 'adplaner'; } }

namespace OCA\AdPlaner\Repository {
    class ShiftPlanRepository {
        public function personalDataForUid(string $uid, int $limit): array {
            if ($uid !== 'self') return ['candidates' => [], 'dayNotes' => [], 'monthPlans' => []];
            return [
                'candidates' => [
                    ['id'=>1,'assistant_uid'=>'self','created_by_uid'=>'planner','created_at'=>'2026-08-01 08:30:00','team_code'=>'A1','work_date'=>'2026-08-12','label'=>'Frühdienst','starts_at'=>'08:00','ends_at'=>'14:00'],
                    ['id'=>2,'assistant_uid'=>'foreign-user','created_by_uid'=>'self','created_at'=>'2026-08-02 09:45:00','team_code'=>'A1','work_date'=>'2026-08-13','label'=>'Spätdienst','starts_at'=>'14:00','ends_at'=>'20:00'],
                ],
                'dayNotes' => [
                    ['id'=>3,'team_code'=>'A1','work_date'=>'2026-08-14','note'=>'Enthält den Namen einer anderen Person','updated_at'=>'2026-08-03 10:15:00'],
                ],
                'monthPlans' => [
                    ['id'=>4,'team_code'=>'A1','plan_month'=>'2026-08','status'=>'approved','updated_at'=>'2026-08-04 11:20:00'],
                ],
            ];
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Privacy\PlanerPersonalDataProvider;
    use OCA\AdPlaner\Privacy\PlanerPrivacyProviderListener;
    use OCA\AdPlaner\Repository\ShiftPlanRepository;
    use OCA\LocalBase\Privacy\PersonalDataProviderRegistryEvent;
    use OCA\LocalBase\Privacy\PersonalDataRequest;
    use OCA\LocalBase\Privacy\PersonalDataSubject;

    $provider = new PlanerPersonalDataProvider(new ShiftPlanRepository());
    $report = $provider->collect(new PersonalDataRequest(new PersonalDataSubject(PersonalDataSubject::NEXTCLOUD_USER, 'self'), 'de', PersonalDataRequest::PURPOSE_SELF_SERVICE, 50));
    $items = array_map(static fn($item): array => $item->toArray(), $report->items());
    if (array_column($items, 'dataType') !== ['Schichtwunsch oder Schichtzuweisung', 'Planungsaktivität', 'Bearbeitete Tagesnotiz', 'Bearbeiteter Monatsplan']) throw new RuntimeException('AD Planer weist nicht alle personenbezogenen Datenklassen getrennt aus.');
    $encoded = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    foreach (['12.08.26','08:00 Uhr','14:00 Uhr','Von einer berechtigten Person eingetragen','13.08.26','02.08.26, 09:45 Uhr','14.08.26','03.08.26, 10:15 Uhr','08.26','Genehmigt','04.08.26, 11:20 Uhr'] as $expected) {
        if (!str_contains($encoded, $expected)) throw new RuntimeException('Menschenlesbare Planerauskunft fehlt: ' . $expected);
    }
    foreach (['foreign-user','planner','Enthält den Namen einer anderen Person','assistant_uid','created_by_uid','Art'] as $forbidden) {
        if (str_contains($encoded, $forbidden)) throw new RuntimeException('Planerauskunft offenbart Drittpersonen oder technische Felder: ' . $forbidden);
    }
    if (!str_contains($encoded, 'Inhalt wird wegen möglicher Angaben zu anderen Personen nicht automatisch ausgegeben')) throw new RuntimeException('Drittpersonenschutz für freie Tagesnotizen fehlt.');
    if (!$report->isComplete() || $report->processing()->toArray()['purposes'] === []) throw new RuntimeException('Vollständigkeits- oder Art.-15-Verarbeitungsangaben fehlen.');

    $foreign = $provider->collect(new PersonalDataRequest(new PersonalDataSubject(PersonalDataSubject::NEXTCLOUD_USER, 'unknown'), 'de', PersonalDataRequest::PURPOSE_SELF_SERVICE, 50));
    if ($foreign->items() !== []) throw new RuntimeException('Eine unbekannte Zielperson erhält fremde Planungsdaten.');

    $registry = new PersonalDataProviderRegistryEvent();
    (new PlanerPrivacyProviderListener($provider))->handle($registry);
    if (array_keys($registry->providers()) !== ['adplaner']) throw new RuntimeException('AD Planer registriert seinen Datenschutzprovider nicht.');

    echo "AD Planer privacy provider test passed\n";
}

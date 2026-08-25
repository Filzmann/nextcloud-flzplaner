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
    use OCA\AdPlaner\Repository\TemporaryAdminAccessRepositoryInterface;
    use OCA\AdPlaner\Privacy\PlanerPrivacyProviderListener;
    use OCA\AdPlaner\Repository\ShiftPlanRepository;
    use OCA\FilzmannDataProtection\PublicApi\V1\DataSubjectRef;
    use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;

    $adminAccess = new class implements TemporaryAdminAccessRepositoryInterface {
        public function replaceActive(string $targetUid,string $grantedBy,\DateTimeImmutable $startsAt,\DateTimeImmutable $endsAt):array{return [];}
        public function revokeActive(string $targetUid,string $revokedBy,\DateTimeImmutable $revokedAt):bool{return false;}
        public function activeFor(string $targetUid,\DateTimeImmutable $at):?array{return null;}
        public function history():array{return [];}
        public function historyForUid(string $uid,int $limit):array{return $uid==='self'?[['id'=>99,'targetUid'=>$uid,'grantedBy'=>'other-admin','startsAt'=>new \DateTimeImmutable('2026-08-25T10:00:00Z'),'endsAt'=>new \DateTimeImmutable('2026-08-25T14:00:00Z'),'revokedAt'=>null,'revokedBy'=>null]]:[];}
    };
    $provider = new PlanerPersonalDataProvider(new ShiftPlanRepository(),$adminAccess);
    $descriptor = $provider->descriptor();
    if ($descriptor->appId() !== 'adplaner' || $descriptor->contractVersion() !== '1.0' || !$descriptor->supportsSubjectType('nextcloud-user')) throw new RuntimeException('AD Planer beschreibt den Standalone-V1-Vertrag nicht korrekt.');
    $subject = new DataSubjectRef('nextcloud-user', 'self');
    $report = $provider->collect(new PersonalDataRequest($subject, 'de', 'access-report', 50, []));
    $items = array_map(static fn($item): array => [
        'categoryId'=>$item->categoryId(),'categoryLabel'=>$item->categoryLabel(),'reference'=>$item->reference(),
        'summary'=>$item->summary(),'purpose'=>$item->purpose(),'source'=>$item->source(),
        'recipientCategories'=>$item->recipientCategories(),'retention'=>$item->retention(),
        'thirdCountryTransfer'=>$item->thirdCountryTransfer(),'automatedDecision'=>$item->automatedDecision(),
        'thirdPartyContentNotice'=>$item->thirdPartyContentNotice(),'attributes'=>$item->attributes(),
    ], $report->entries());
    if (array_column($items, 'categoryLabel') !== ['Zeitlich begrenzter Admin-Vollzugriff', 'Schichtwunsch oder Schichtzuweisung', 'Planungsaktivität', 'Bearbeitete Tagesnotiz', 'Bearbeiteter Monatsplan']) throw new RuntimeException('AD Planer weist nicht alle personenbezogenen Datenklassen getrennt aus.');
    $encoded = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    foreach (['Admin-Vollzugriff','Ziel der Vollzugriffsfreigabe','12.08.26','08:00 Uhr','14:00 Uhr','Von einer berechtigten Person eingetragen','13.08.26','02.08.26, 09:45 Uhr','14.08.26','03.08.26, 10:15 Uhr','08.26','Genehmigt','04.08.26, 11:20 Uhr'] as $expected) {
        if (!str_contains($encoded, $expected)) throw new RuntimeException('Menschenlesbare Planerauskunft fehlt: ' . $expected);
    }
    foreach (['foreign-user','planner','other-admin','Enthält den Namen einer anderen Person','assistant_uid','created_by_uid','Art'] as $forbidden) {
        if (str_contains($encoded, $forbidden)) throw new RuntimeException('Planerauskunft offenbart Drittpersonen oder technische Felder: ' . $forbidden);
    }
    if (!str_contains($encoded, 'Inhalt wird wegen möglicher Angaben zu anderen Personen nicht automatisch ausgegeben')) throw new RuntimeException('Drittpersonenschutz für freie Tagesnotizen fehlt.');
    if ($report->status() !== 'complete' || $items[0]['recipientCategories'] === []) throw new RuntimeException('Vollständigkeits- oder Art.-15-Verarbeitungsangaben fehlen.');

    $foreign = $provider->collect(new PersonalDataRequest(new DataSubjectRef('nextcloud-user', 'unknown'), 'de', 'access-report', 50, []));
    if ($foreign->status() !== 'not_applicable' || $foreign->entries() !== []) throw new RuntimeException('Eine unbekannte Zielperson erhält fremde Planungsdaten.');
    $unsupported = $provider->collect(new PersonalDataRequest(new DataSubjectRef('external-applicant', 'self'), 'de', 'access-report', 50, []));
    if ($unsupported->status() !== 'not_applicable' || $unsupported->entries() !== []) throw new RuntimeException('Ein nicht unterstützter Subject-Typ erhält Planungsdaten.');
    if ($provider->collect(new PersonalDataRequest($subject, 'de', 'access-report', 1, []))->status() !== 'partial') throw new RuntimeException('Ein begrenzter Planungsbericht behauptet Vollständigkeit.');
    try {
        $provider->collect((new PersonalDataRequest($subject, 'de', 'access-report', 50, ['adplaner'=>'opaque']))->forProvider('adplaner', 50));
        throw new RuntimeException('Ein unbekannter Provider-Cursor wurde akzeptiert.');
    } catch (InvalidArgumentException) {}

    $registry = new RegisterPersonalDataProvidersEvent();
    (new PlanerPrivacyProviderListener($provider))->handle($registry);
    if (array_keys($registry->providers()) !== ['adplaner']) throw new RuntimeException('AD Planer registriert seinen Datenschutzprovider nicht.');
    $application = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/AppInfo/Application.php');
    if (!str_contains($application, 'registerEventListener(RegisterPersonalDataProvidersEvent::class, PlanerPrivacyProviderListener::class)') || str_contains($application, 'PersonalDataProviderRegistryEvent')) throw new RuntimeException('AD Planer registriert den Provider nicht ausschließlich am Standalone-V1-Event.');

    echo "AD Planer privacy provider test passed\n";
}

<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { class Event { public function __construct() {} } interface IEventListener { public function handle(Event $event): void; } }
namespace OCA\FlzPlaner\AppInfo { final class Application { public const APP_ID = 'flzplaner'; } }

namespace {
    require_once __DIR__ . '/bootstrap.php';

    use OCA\FlzPlaner\Listener\IntegrationCapabilityQueryListener;
    use OCA\FlzPlaner\Privacy\PlanerPrivacyProviderListener;
    use OCA\FlzOrganization\PublicApi\V1\IntegrationCapabilities;
    use OCA\FlzOrganization\PublicApi\V1\IntegrationCapabilityQueryEvent;

    $event = new IntegrationCapabilityQueryEvent(IntegrationCapabilities::all());
    (new IntegrationCapabilityQueryListener())->handle($event);
    if ($event->providersFor(IntegrationCapabilities::ASSISTANT_SCHEDULE_READ) !== ['flzplaner']) throw new RuntimeException('Assistenzplanfähigkeit fehlt.');
    if ($event->isAvailable(IntegrationCapabilities::ABSENCE_READ)) throw new RuntimeException('Assistenzplaner meldet eine fremde Fähigkeit.');

    $application = file_get_contents(__DIR__ . '/../lib/AppInfo/Application.php');
    if ($application === false || !str_contains($application, 'PlanerPrivacyProviderListener::class')) throw new RuntimeException('Filzmann Assistenzplanung registriert den Datenschutzprovider nicht im Bootstrap.');

    echo "Filzmann Assistenzplanung capability listener test passed\n";
}

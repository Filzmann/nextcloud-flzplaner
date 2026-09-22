<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventListener { public function handle(Event $event): void; }
}

namespace OCA\AdPlaner\AppInfo {
    final class Application { public const APP_ID = 'adplaner'; }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Privacy\PlanerProcessingMetadataProvider;
    use OCA\AdPlaner\Privacy\PlanerProcessingMetadataProviderListener;
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
    use OCP\EventDispatcher\Event;

    $provider = new PlanerProcessingMetadataProvider();
    $catalog = $provider->catalog();
    $descriptor = $provider->descriptor();
    if ($descriptor->appId() !== 'adplaner' || $descriptor->displayName() !== 'Assistenz Dienstplanung' || $descriptor->contractVersion() !== '1.0') {
        throw new RuntimeException('Der Processing-Metadata-Provider beschreibt AD Planer nicht korrekt.');
    }
    if ($catalog->appId() !== 'adplaner') {
        throw new RuntimeException('Processing-Metadata-Provider und Katalog verwenden nicht die kanonische App-ID.');
    }
    if ($catalog->processingIds() !== ['shift_planning_management', 'temporary_admin_full_access']) {
        throw new RuntimeException('Der app-lokale Processing-Katalog ist unvollständig.');
    }
    if (array_key_exists('personal_runtime_data', $catalog->toArray())) {
        throw new RuntimeException('Der Processing-Katalog enthält personenbezogene Laufzeitdaten.');
    }
    $encodedCatalog = json_encode($catalog->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if (!str_contains($encodedCatalog, 'App-lokaler Freigabevorgang durch Datenschutzbeauftragte im AD Planer')
        || str_contains($encodedCatalog, 'Freigabevorgang im Nextcloud-Adminbereich')) {
        throw new RuntimeException('Der Processing-Katalog projiziert die DPO-Freigabesteuerung nicht korrekt.');
    }

    $registration = new RegisterProcessingMetadataProvidersEvent();
    $listener = new PlanerProcessingMetadataProviderListener($provider);
    $listener->handle(new Event());
    if ($registration->providers() !== []) {
        throw new RuntimeException('Ein fremdes Event registriert den Processing-Metadata-Provider.');
    }
    $listener->handle($registration);
    if (($registration->providers()['adplaner'] ?? null) !== $provider) {
        throw new RuntimeException('Der Processing-Metadata-Provider wird nicht lazy registriert.');
    }

    $application = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/AppInfo/Application.php');
    if (!str_contains($application, 'registerEventListener(RegisterProcessingMetadataProvidersEvent::class, PlanerProcessingMetadataProviderListener::class)')) {
        throw new RuntimeException('Der Bootstrap registriert den Processing-Metadata-Provider nicht am öffentlichen V1-Event.');
    }

    echo "AD Planer processing metadata provider test passed\n";
}

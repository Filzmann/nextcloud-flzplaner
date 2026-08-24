<?php

declare(strict_types=1);

namespace OCA\AdPlaner\AppInfo;

use OCA\AdPlaner\Listener\IntegrationCapabilityQueryListener;
use OCA\AdPlaner\Listener\StandaloneNavigationListener;
use OCA\AdPlaner\Privacy\PlanerPrivacyProviderListener;
use OCA\LocalBase\Integration\IntegrationCapabilityQueryEvent;
use OCA\LocalBase\Privacy\PersonalDataProviderRegistryEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** Zweck: Registriert Assistenzplanfähigkeit und Standalone-Navigation im Nextcloud-Bootstrap. */
class Application extends App implements IBootstrap {
    public const APP_ID = 'adplaner';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(IntegrationCapabilityQueryEvent::class, IntegrationCapabilityQueryListener::class);
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerEventListener(PersonalDataProviderRegistryEvent::class, PlanerPrivacyProviderListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}

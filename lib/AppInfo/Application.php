<?php

declare(strict_types=1);

namespace OCA\AdPlaner\AppInfo;

use OCA\AdPlaner\Listener\IntegrationCapabilityQueryListener;
use OCA\AdPlaner\Listener\StandaloneNavigationListener;
use OCA\AdPlaner\Privacy\PlanerPrivacyProviderListener;
use OCA\AdPlaner\Permission\PlanerPermissionProviderListener;
use OCA\AdPlaner\Permission\PlanerPermissionSourceInterface;
use OCA\AdPlaner\Permission\NextcloudPlanerPermissionSource;
use OCA\AdPlaner\Repository\TemporaryAdminAccessRepository;
use OCA\AdPlaner\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\AdPlaner\Service\TemporaryAdminAccessChecker;
use OCA\AdPlaner\Service\TemporaryAdminAccessService;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCA\LocalBase\Integration\IntegrationCapabilityQueryEvent;
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
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, PlanerPrivacyProviderListener::class);
        $context->registerEventListener(RegisterPermissionProvidersEvent::class, PlanerPermissionProviderListener::class);
        $context->registerServiceAlias(PlanerPermissionSourceInterface::class, NextcloudPlanerPermissionSource::class);
        $context->registerServiceAlias(TemporaryAdminAccessChecker::class, TemporaryAdminAccessService::class);
        $context->registerServiceAlias(TemporaryAdminAccessRepositoryInterface::class, TemporaryAdminAccessRepository::class);
    }

    public function boot(IBootContext $context): void {
    }
}

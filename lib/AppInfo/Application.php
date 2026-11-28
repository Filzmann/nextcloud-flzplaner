<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\AppInfo;

use OCA\FlzPlaner\Listener\IntegrationCapabilityQueryListener;
use OCA\FlzPlaner\Listener\ScheduleConflictQueryListener;
use OCA\FlzPlaner\Listener\StandaloneNavigationListener;
use OCA\FlzPlaner\Privacy\PlanerProcessingMetadataProviderListener;
use OCA\FlzPlaner\Privacy\PlanerPrivacyProviderListener;
use OCA\FlzPlaner\Permission\PlanerPermissionProviderListener;
use OCA\FlzPlaner\Permission\PlanerPermissionSourceInterface;
use OCA\FlzPlaner\Permission\NextcloudPlanerPermissionSource;
use OCA\FlzPlaner\Repository\TemporaryAdminAccessRepository;
use OCA\FlzPlaner\Repository\TemporaryAdminAccessRepositoryInterface;
use OCA\FlzPlaner\Service\TemporaryAdminAccessChecker;
use OCA\FlzPlaner\Service\TemporaryAdminAccessService;
use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCA\FlzOrganization\PublicApi\V1\IntegrationCapabilityQueryEvent;
use OCA\FlzOrganization\PublicApi\V1\ScheduleConflictQueryEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** Zweck: Registriert Assistenzplanfähigkeit und Standalone-Navigation im Nextcloud-Bootstrap. */
class Application extends App implements IBootstrap {
    public const APP_ID = 'flzplaner';

    public function __construct(array $urlParams = []) {
        require_once dirname(__DIR__, 2) . '/vendor/flz-shared/php/vendor/autoload.php';
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(IntegrationCapabilityQueryEvent::class, IntegrationCapabilityQueryListener::class);
        $context->registerEventListener(ScheduleConflictQueryEvent::class, ScheduleConflictQueryListener::class);
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, PlanerPrivacyProviderListener::class);
        $context->registerEventListener(RegisterProcessingMetadataProvidersEvent::class, PlanerProcessingMetadataProviderListener::class);
        $context->registerEventListener(RegisterPermissionProvidersEvent::class, PlanerPermissionProviderListener::class);
        $context->registerServiceAlias(PlanerPermissionSourceInterface::class, NextcloudPlanerPermissionSource::class);
        $context->registerServiceAlias(TemporaryAdminAccessChecker::class, TemporaryAdminAccessService::class);
        $context->registerServiceAlias(TemporaryAdminAccessRepositoryInterface::class, TemporaryAdminAccessRepository::class);
    }

    public function boot(IBootContext $context): void {
    }
}

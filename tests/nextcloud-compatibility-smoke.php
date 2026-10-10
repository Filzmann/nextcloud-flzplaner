<?php

declare(strict_types=1);

use OCA\FlzPlaner\Service\TemporaryAdminAccessService;

return [
    'providerRegistrations' => [
        'flz_data_protection' => [
            OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
        ],
        'flz_permission_matrix' => [
            OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent::class,
        ],
    ],
    'uiPath' => '/index.php/apps/flzplaner/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    'grantService' => TemporaryAdminAccessService::class,
    'grantManagerGroups' => ['Datenschutzbeauftragte'],
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(TemporaryAdminAccessService::class)
        ->hasActiveGrant($uid),
    'apiSmokes' => [
        ['/index.php/apps/flzplaner/api/state', [200]],
    ],
];

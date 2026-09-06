<?php

declare(strict_types=1);

use OCA\AdPlaner\Service\TemporaryAdminAccessService;

return [
    'uiPath' => '/index.php/apps/adplaner/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    'grantService' => TemporaryAdminAccessService::class,
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(TemporaryAdminAccessService::class)
        ->hasActiveGrant($uid),
    'apiSmokes' => [
        ['/index.php/apps/adplaner/api/state', [200]],
    ],
];

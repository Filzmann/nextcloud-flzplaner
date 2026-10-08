<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

use OCA\FlzPlaner\AppInfo\Application;
use OCA\LocalBase\Service\AppLogger;
use Throwable;

class FlzPlanerLogger {
    public function __construct(
        private AppLogger $logger
    ) {
    }

    public function error(string $action, Throwable $exception, array $context = []): void {
        $this->logger->error(Application::APP_ID, 'FlzPlaner', $action, $exception, $context);
    }
}

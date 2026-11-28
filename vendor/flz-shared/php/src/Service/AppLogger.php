<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Vendor\Filzmann\Shared\Service;

use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class AppLogger {
    public function __construct(
        private LoggerInterface $logger,
        private IUserSession $userSession
    ) {
    }

    public function error(string $appId, string $appLabel, string $action, Throwable $exception, array $context = []): void {
        $safeContext = $this->safeContext($context);
        $safeContext['app'] = $appId;
        $safeContext['action'] = $action;
        $safeContext['exception_class'] = get_class($exception);
        $safeContext['exception_message'] = $exception->getMessage();

        $user = $this->userSession->getUser();
        if ($user !== null) {
            $safeContext['user_id'] = $user->getUID();
        }

        $this->logger->error($appLabel . ' error during ' . $action, $safeContext);
    }

    public function info(string $appId, string $message, array $context = []): void {
        $safeContext = $this->safeContext($context);
        $safeContext['app'] = $appId;

        $this->logger->info($message, $safeContext);
    }

    private function safeContext(array $context): array {
        $safeContext = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safeContext[(string)$key] = $value;
            }
        }

        return $safeContext;
    }
}

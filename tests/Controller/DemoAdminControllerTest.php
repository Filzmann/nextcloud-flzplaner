<?php

declare(strict_types=1);

namespace OCP\AppFramework {
    if (!class_exists(Controller::class)) {
        class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} }
    }
    if (!class_exists(Http::class)) {
        final class Http {
            public const STATUS_BAD_REQUEST = 400;
            public const STATUS_FORBIDDEN = 403;
            public const STATUS_INTERNAL_SERVER_ERROR = 500;
        }
    }
}

namespace OCP\AppFramework\Http {
    if (!class_exists(JSONResponse::class)) {
        final class JSONResponse {
            public function __construct(public array $data = [], public int $status = 200) {}
            public function getData(): array { return $this->data; }
            public function getStatus(): int { return $this->status; }
        }
    }
}

namespace OCP {
    if (!interface_exists(IRequest::class)) {
        interface IRequest {}
    }
    if (!interface_exists(IUserSession::class)) {
        interface IUserSession { public function getUser(); }
    }
    if (!interface_exists(IGroupManager::class)) {
        interface IGroupManager { public function isAdmin($uid); }
    }
}

namespace Psr\Log {
    if (!interface_exists(LoggerInterface::class)) {
        interface LoggerInterface { public function error(string|\Stringable $message, array $context = []): void; }
    }
}

namespace OCA\AdPlaner\AppInfo {
    if (!class_exists(Application::class)) {
        final class Application { public const APP_ID = 'adplaner'; }
    }
}

namespace OCA\AdPlaner\Service {
    if (!class_exists(PlanerDemoPackService::class)) {
        class PlanerDemoPackService {
            public int $installCalls = 0;
            public bool $throwOnInstall = false;
            public function install(): array {
                $this->installCalls++;
                if ($this->throwOnInstall) {
                    throw new \RuntimeException('SQL password=synthetic-secret');
                }
                return ['accounts' => [], 'teams' => ['A', 'B', 'C']];
            }
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Controller\DemoAdminController;
    use OCA\AdPlaner\Service\PlanerDemoPackService;
    use OCP\IGroupManager;
    use OCP\IRequest;
    use OCP\IUserSession;
    use Psr\Log\LoggerInterface;

    $request = new class implements IRequest {};
    $session = new class implements IUserSession {
        public function getUser(): object {
            return new class {
                public function getUID(): string { return 'admin-test'; }
            };
        }
    };
    $groups = new class implements IGroupManager {
        public bool $admin = true;
        public function isAdmin($uid): bool { return $this->admin && $uid === 'admin-test'; }
    };
    $logger = new class implements LoggerInterface {
        public array $errors = [];
        public function error(string|\Stringable $message, array $context = []): void {
            $this->errors[] = compact('message', 'context');
        }
    };
    $demoPack = new PlanerDemoPackService();
    $controller = new DemoAdminController($request, $session, $groups, $demoPack, $logger);

    $unconfirmed = $controller->install(false);
    if ($unconfirmed->getStatus() !== 400 || $demoPack->installCalls !== 0) {
        throw new RuntimeException('Eine unbestätigte Demo-Installation muss ohne Mutation mit HTTP 400 abgewiesen werden.');
    }

    $confirmed = $controller->install(true);
    if ($confirmed->getStatus() !== 200 || $demoPack->installCalls !== 1 || ($confirmed->getData()['result']['teams'] ?? []) !== ['A', 'B', 'C']) {
        throw new RuntimeException('Eine bestätigte Admin-Installation muss den Demo-Service genau einmal ausführen.');
    }

    $controller->install(false);
    if ($demoPack->installCalls !== 1) {
        throw new RuntimeException('Jede weitere Demo-Installation muss erneut ausdrücklich bestätigt werden.');
    }

    foreach (['true', '1', 1, null] as $manipulatedConfirmation) {
        $manipulated = $controller->install($manipulatedConfirmation);
        if ($manipulated->getStatus() !== 400 || $demoPack->installCalls !== 1) {
            throw new RuntimeException('Nur das JSON-Boolean true darf die Demo-Installation bestätigen.');
        }
    }

    $groups->admin = false;
    $forbidden = $controller->install(true);
    if ($forbidden->getStatus() !== 403 || $demoPack->installCalls !== 1) {
        throw new RuntimeException('Eine bestätigte Nicht-Admin-Anfrage muss ohne Mutation mit HTTP 403 abgewiesen werden.');
    }

    $groups->admin = true;
    $demoPack->throwOnInstall = true;
    $failed = $controller->install(true);
    if ($failed->getStatus() !== 500 || ($failed->getData()['error'] ?? '') !== 'Demo-Daten konnten nicht installiert werden.') {
        throw new RuntimeException('Interne Demo-Fehler müssen ohne technische Details als generische HTTP-500-Antwort erscheinen.');
    }
    if (str_contains(json_encode($failed->getData()), 'synthetic-secret') || count($logger->errors) !== 1) {
        throw new RuntimeException('Interne Fehlermeldungen dürfen nur im Serverlog und nie in der API-Antwort landen.');
    }

    echo 'AdPlaner demo admin controller tests passed' . PHP_EOL;
}

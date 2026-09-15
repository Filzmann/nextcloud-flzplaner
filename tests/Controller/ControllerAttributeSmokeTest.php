<?php

declare(strict_types=1);

namespace OCP {
    if (!interface_exists(IRequest::class)) {
        interface IRequest {}
    }
}

namespace OCP\AppFramework {
    if (!class_exists(Controller::class)) {
        class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} }
    }
}

namespace OCP\AppFramework\Http {
    if (!class_exists(Response::class)) {
        class Response {}
    }
    if (!class_exists(DataResponse::class)) {
        class DataResponse extends Response { public function __construct(mixed $data = [], int $status = 200) {} }
    }
    if (!class_exists(TemplateResponse::class)) {
        class TemplateResponse extends Response { public function __construct(string $appName, string $templateName) {} }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    if (!class_exists(NoAdminRequired::class)) {
        #[\Attribute(\Attribute::TARGET_METHOD)] class NoAdminRequired {}
    }
    if (!class_exists(NoCSRFRequired::class)) {
        #[\Attribute(\Attribute::TARGET_METHOD)] class NoCSRFRequired {}
    }
}

namespace {
}

namespace OCA\AdPlaner\AppInfo {
    if (!class_exists(Application::class)) {
        final class Application {
            public const APP_ID = 'adplaner';
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/bootstrap.php';

    use OCA\AdPlaner\Controller\ApiController;
    use OCA\AdPlaner\Controller\PageController;
    use OCP\AppFramework\Http\Attribute\NoAdminRequired;
    use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

    $pageIndex = new \ReflectionMethod(PageController::class, 'index');
    if ($pageIndex->getAttributes(NoCSRFRequired::class) === []) {
        throw new \RuntimeException('Page index should be loadable without a CSRF header.');
    }
    if ($pageIndex->getAttributes(NoAdminRequired::class) === []) {
        throw new \RuntimeException('Page index should be available to regular users.');
    }

    $readActions = [
        'state',
        'monthPlan',
    ];
    foreach ($readActions as $action) {
        $method = new \ReflectionMethod(ApiController::class, $action);
        if ($method->getAttributes(NoAdminRequired::class) === []) {
            throw new \RuntimeException($action . ' should be available to regular users.');
        }
        if ($method->getAttributes(NoCSRFRequired::class) === []) {
            throw new \RuntimeException($action . ' should be readable without a CSRF header.');
        }
    }

    $writeActions = [
        'saveTeamSettings',
        'saveDayNote',
        'addShiftCandidate',
        'removeShiftCandidate',
        'updateCandidateMetadata',
        'savePersonalWorkload',
        'savePersonalRegularShifts',
        'transitionMonthStatus',
        'reportFixedConflict',
        'resolveFixedConflict',
    ];

    foreach ($writeActions as $action) {
        $method = new \ReflectionMethod(ApiController::class, $action);
        if ($method->getAttributes(NoAdminRequired::class) === []) {
            throw new \RuntimeException($action . ' should be available to regular users.');
        }
        if ($method->getAttributes(NoCSRFRequired::class) !== []) {
            throw new \RuntimeException($action . ' should keep the default CSRF protection.');
        }
    }

    echo 'AdPlaner controller attribute smoke tests passed' . PHP_EOL;
}

<?php

declare(strict_types=1);

namespace {
    if (!class_exists(\OCP\AppFramework\Controller::class)) {
        eval('namespace OCP\AppFramework; class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} }');
    }
    if (!interface_exists(\OCP\IRequest::class)) {
        eval('namespace OCP; interface IRequest {}');
    }
    if (!class_exists(\OCP\AppFramework\Http\Response::class)) {
        eval('namespace OCP\AppFramework\Http; class Response {}');
    }
    if (!class_exists(\OCP\AppFramework\Http\DataResponse::class)) {
        eval('namespace OCP\AppFramework\Http; class DataResponse extends Response { public function __construct(mixed $data = [], int $status = 200) {} }');
    }
    if (!class_exists(\OCP\AppFramework\Http\TemplateResponse::class)) {
        eval('namespace OCP\AppFramework\Http; class TemplateResponse extends Response { public function __construct(string $appName, string $templateName) {} }');
    }
    if (!class_exists(\OCP\AppFramework\Http\Attribute\NoAdminRequired::class)) {
        eval('namespace OCP\AppFramework\Http\Attribute; #[\Attribute(\Attribute::TARGET_METHOD)] class NoAdminRequired {}');
    }
    if (!class_exists(\OCP\AppFramework\Http\Attribute\NoCSRFRequired::class)) {
        eval('namespace OCP\AppFramework\Http\Attribute; #[\Attribute(\Attribute::TARGET_METHOD)] class NoCSRFRequired {}');
    }
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

    $apiActions = [
        'state',
        'monthPlan',
        'saveTeamSettings',
        'saveDayNote',
        'addShiftCandidate',
        'removeShiftCandidate',
        'transitionMonthStatus',
    ];

    foreach ($apiActions as $action) {
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

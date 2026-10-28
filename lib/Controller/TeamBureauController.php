<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Controller;

use OCA\FlzPlaner\AppInfo\Application;
use OCA\FlzPlaner\Service\TeamBureauAssignmentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

final class TeamBureauController extends Controller {
    public function __construct(IRequest $request, private TeamBureauAssignmentService $assignments) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function save(string $teamCode, string $bureauKey): DataResponse {
        try {
            return new DataResponse(['ok' => true, 'settings' => $this->assignments->assign($teamCode, $bureauKey)]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        } catch (\DomainException $e) {
            return new DataResponse(['ok' => false, 'error' => $e->getMessage()], 403);
        }
    }
}

<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Controller;

use OCA\FlzPlaner\AppInfo\Application;
use OCA\FlzPlaner\Service\TeamBureauAssignmentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

final class TeamBureauController extends Controller {
    public function __construct(IRequest $request, private TeamBureauAssignmentService $assignments, private ?IL10N $l10n = null) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }

    #[NoAdminRequired]
    public function save(string $teamCode, string $bureauKey): DataResponse {
        try {
            return new DataResponse(['ok' => true, 'settings' => $this->assignments->assign($teamCode, $bureauKey)]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['ok' => false, 'error' => $this->t($e->getMessage())], 400);
        } catch (\DomainException $e) {
            return new DataResponse(['ok' => false, 'error' => $this->t($e->getMessage())], 403);
        }
    }
}

<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Controller;

use OCA\AdPlaner\AppInfo\Application;
use OCA\AdPlaner\Service\AdPlanerLogger;
use OCA\AdPlaner\Service\ScheduleService;
use OCA\AdPlaner\Service\TeamAccessService;
use OCA\AdPlaner\Service\TeamSettingsService;
use OCA\LocalBase\Controller\ApiResponder;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private TeamAccessService $teamAccess,
        private TeamSettingsService $teamSettings,
        private ScheduleService $scheduleService,
        private AdPlanerLogger $logger,
        private ApiResponder $responder
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function state(): DataResponse {
        return $this->responder->respond(function (): array {
            $uid = $this->teamAccess->currentUserId();

            return [
                'currentUser' => ['uid' => $uid],
                'teams' => array_map(
                    static fn($team): array => $team->toArray(),
                    $this->teamAccess->teamsForCurrentUser()
                ),
                'organization' => $this->teamAccess->organizationContract(),
                'defaultMonth' => date('Y-m'),
                'defaultYear' => (int)date('Y'),
                'notice' => 'Assistenzteams und Koordinationsrechte folgen den gemeinsamen AD-Organisationseinstellungen.',
            ];
        }, [$this->logger, 'error'], 'state');
    }

    #[NoAdminRequired]
    public function monthPlan(string $teamCode, string $month): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $month): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);

            return $this->scheduleService->monthPlan($team, $month, $this->teamAccess->currentUserId());
        }, [$this->logger, 'error'], 'month_plan', ['team_code' => $teamCode, 'month' => $month]);
    }

    #[NoAdminRequired]
    public function transitionMonthStatus(string $teamCode, string $month, string $targetStatus): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $month, $targetStatus): array {
            $team = $this->teamAccess->assertCanCoordinate($teamCode);
            $status = $this->scheduleService->transitionMonthStatus(
                $team,
                $month,
                $targetStatus,
                $this->teamAccess->currentUserId()
            );

            return ['ok' => true, 'status' => $status];
        }, [$this->logger, 'error'], 'transition_month_status', [
            'team_code' => $teamCode,
            'month' => $month,
            'target_status' => $targetStatus,
        ]);
    }

    #[NoAdminRequired]
    public function saveTeamSettings(
        string $teamCode,
        string $displayName = '',
        string $meetingDay = '',
        string $shiftsJson = ''
    ): DataResponse {
        return $this->responder->respond(function () use (
            $teamCode,
            $displayName,
            $meetingDay,
            $shiftsJson
        ): array {
            $team = $this->teamAccess->assertCanCoordinate($teamCode);
            $config = ['meetingDay' => $meetingDay, 'shifts' => $this->decodeShiftsJson($shiftsJson)];

            $settings = $this->teamSettings->save($team->code, $displayName, $config);

            return ['ok' => true, 'settings' => $settings];
        }, [$this->logger, 'error'], 'save_team_settings', ['team_code' => $teamCode]);
    }

    #[NoAdminRequired]
    public function saveDayNote(string $teamCode, string $month, string $workDate, string $note = ''): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $month, $workDate, $note): array {
            $team = $this->teamAccess->assertCanCoordinate($teamCode);
            $this->scheduleService->saveDayNote($team, $month, $workDate, $note, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, [$this->logger, 'error'], 'save_day_note', [
            'team_code' => $teamCode,
            'month' => $month,
            'work_date' => $workDate,
        ]);
    }

    #[NoAdminRequired]
    public function addShiftCandidate(string $teamCode, string $month, int $slotId, string $targetUid = ''): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $month, $slotId, $targetUid): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $this->scheduleService->addCandidate($team, $month, $slotId, $targetUid, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, [$this->logger, 'error'], 'add_shift_candidate', [
            'team_code' => $teamCode,
            'month' => $month,
            'slot_id' => $slotId,
        ]);
    }

    #[NoAdminRequired]
    public function removeShiftCandidate(string $teamCode, string $month, int $slotId, string $targetUid = ''): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $month, $slotId, $targetUid): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $this->scheduleService->removeCandidate($team, $month, $slotId, $targetUid, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, [$this->logger, 'error'], 'remove_shift_candidate', [
            'team_code' => $teamCode,
            'month' => $month,
            'slot_id' => $slotId,
        ]);
    }

    private function decodeShiftsJson(string $shiftsJson): array {
        $shiftsJson = trim($shiftsJson);
        if ($shiftsJson === '') {
            throw new \InvalidArgumentException('Mindestens eine Schicht muss übergeben werden.');
        }

        $decoded = json_decode($shiftsJson, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Schichten konnten nicht gelesen werden.');
        }

        return $decoded;
    }
}

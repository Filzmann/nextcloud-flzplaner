<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Controller;

use OCA\FlzPlaner\AppInfo\Application;
use OCA\FlzPlaner\Service\FlzPlanerLogger;
use OCA\FlzPlaner\Service\ScheduleService;
use OCA\FlzPlaner\Service\TeamAccessService;
use OCA\FlzPlaner\Service\TeamSettingsService;
use OCA\FlzPlaner\Service\WorkloadPreferenceService;
use OCA\FlzPlaner\Service\FixedShiftService;
use OCA\LocalBase\Controller\ApiResponder;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private TeamAccessService $teamAccess,
        private TeamSettingsService $teamSettings,
        private ScheduleService $scheduleService,
        private FlzPlanerLogger $logger,
        private ApiResponder $responder,
        private WorkloadPreferenceService $workloadPreferences,
        private FixedShiftService $fixedShifts
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired, NoCSRFRequired]
    public function state(): DataResponse {
        return $this->responder->respond(function (): array {
            $uid = $this->teamAccess->currentUserId();

            return [
                'currentUser' => ['uid' => $uid],
                'teams' => array_map(
                    fn($team): array => [
                        ...$team->toArray(),
                        'personalWorkload' => $this->workloadPreferences->personal($team, $uid),
                        'canSetPersonalWorkload' => $team->assistantByUid($uid)?->canReceiveShifts ?? false,
                        'personalRegularShifts' => $this->fixedShifts->personal($team, $uid),
                        'canSetRegularShifts' => $team->assistantByUid($uid)?->canReceiveShifts ?? false,
                    ],
                    $this->teamAccess->teamsForCurrentUser()
                ),
                'organization' => $this->teamAccess->organizationContract(),
                'defaultMonth' => date('Y-m'),
                'defaultYear' => (int)date('Y'),
                'notice' => 'Assistenzteams und Koordinationsrechte folgen den gemeinsamen Filzmann-Organisationseinstellungen.',
            ];
        }, [$this->logger, 'error'], 'state');
    }

    #[NoAdminRequired, NoCSRFRequired]
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

    #[NoAdminRequired]
    public function updateCandidateMetadata(string $teamCode, string $month, int $slotId, string $preference = 'neutral', string $note = ''): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $month, $slotId, $preference, $note): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $this->scheduleService->updateCandidateMetadata($team, $month, $slotId, $preference, $note, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, [$this->logger, 'error'], 'update_candidate_metadata', [
            'team_code' => $teamCode,
            'month' => $month,
            'slot_id' => $slotId,
        ]);
    }

    #[NoAdminRequired]
    public function savePersonalWorkload(string $teamCode, string $weeklyMin = '', string $weeklyMax = '', string $monthlyMin = '', string $monthlyMax = ''): DataResponse {
        return $this->responder->respond(function () use ($teamCode, $weeklyMin, $weeklyMax, $monthlyMin, $monthlyMax): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $limits = $this->workloadPreferences->savePersonal($team, $this->teamAccess->currentUserId(), $weeklyMin, $weeklyMax, $monthlyMin, $monthlyMax);

            return ['ok' => true, 'limits' => $limits];
        }, [$this->logger, 'error'], 'save_personal_workload', ['team_code' => $teamCode]);
    }

    #[NoAdminRequired]
    public function savePersonalRegularShifts(string $teamCode, string $regularShiftsJson = '[]'): DataResponse {
        return $this->responder->respond(function () use ($teamCode,$regularShiftsJson): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $rules = json_decode($regularShiftsJson,true);
            if (!is_array($rules) || !array_is_list($rules)) throw new \InvalidArgumentException('Regelmäßige Schichten konnten nicht gelesen werden.');
            return ['ok'=>true,'regularShifts'=>$this->fixedShifts->savePersonal($team,$this->teamAccess->currentUserId(),$rules)];
        },[$this->logger,'error'],'save_personal_regular_shifts',['team_code'=>$teamCode]);
    }

    #[NoAdminRequired]
    public function reportFixedConflict(string $teamCode,string $month,int $slotId): DataResponse {
        return $this->responder->respond(function() use($teamCode,$month,$slotId): array {
            $team=$this->teamAccess->assertTeamAccess($teamCode);
            $this->scheduleService->reportFixedConflict($team,$month,$slotId,$this->teamAccess->currentUserId());
            return ['ok'=>true];
        },[$this->logger,'error'],'report_fixed_conflict',['team_code'=>$teamCode,'month'=>$month,'slot_id'=>$slotId]);
    }

    #[NoAdminRequired]
    public function resolveFixedConflict(string $teamCode,string $month,int $slotId,string $keptUid=''): DataResponse {
        return $this->responder->respond(function() use($teamCode,$month,$slotId,$keptUid): array {
            $team=$this->teamAccess->assertCanCoordinate($teamCode);
            $this->scheduleService->resolveFixedConflict($team,$month,$slotId,$keptUid,$this->teamAccess->currentUserId());
            return ['ok'=>true];
        },[$this->logger,'error'],'resolve_fixed_conflict',['team_code'=>$teamCode,'month'=>$month,'slot_id'=>$slotId]);
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

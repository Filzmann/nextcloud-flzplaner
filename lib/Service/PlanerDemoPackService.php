<?php

declare(strict_types=1);

namespace OCA\FlzPlaner\Service;

use OCA\LocalBase\Organization\FlzOrganizationDefinition;
use OCA\LocalBase\Organization\FlzOrganizationSettingsService;
use OCA\LocalBase\Service\DemoAccountProvisioningService;

/**
 * Zweck: Installiert die neutralen Assistenzteams Team A, Team B und Team C mit lokalen Demokonten.
 * Vertrag: Bestehende fremde Konten und read-only LDAP-Gruppen werden vom LocalBase-Preflight vor jeder Änderung abgewiesen.
 */
final class PlanerDemoPackService {
    public function __construct(
        private DemoAccountProvisioningService $accounts,
        private TeamSettingsService $settings,
        private ShiftConfigService $shiftConfig,
        private ?FlzOrganizationSettingsService $organization = null,
    ) {}

    /** @return array{accounts:array,teams:list<string>} */
    public function install(): array {
        $definition = $this->organization?->definition() ?? FlzOrganizationDefinition::defaults();
        $teamCodes = ['A', 'B', 'C'];
        $ebGroup = $definition->roleGroupId('eb');
        $fixtures = [];
        foreach ($teamCodes as $index => $teamCode) {
            $teamGroup = $definition->teamGroupPrefix() . $teamCode;
            $coordinatorNames = ['Enna Busch', 'Emil Weber', 'Eda Sommer'];
            $fixtures[] = [
                'uid' => 'flz-demo-eb-' . strtolower($teamCode),
                'displayName' => $coordinatorNames[$index] . " (EB, Team {$teamCode})",
                'groups' => [$ebGroup, $teamGroup],
            ];
            foreach ([1, 2] as $number) {
                $fixtures[] = [
                    'uid' => 'flz-demo-assistenz-' . strtolower($teamCode) . $number,
                    'displayName' => "Demo Assistenz {$teamCode}{$number} (Team {$teamCode})",
                    'groups' => [$teamGroup],
                ];
            }
        }

        $accounts = $this->accounts->provision('flz-full-suite-demo', $fixtures);
        foreach ($teamCodes as $teamCode) {
            $this->settings->save($teamCode, "Team {$teamCode}", $this->shiftConfig->defaults());
        }
        return ['accounts' => $accounts, 'teams' => $teamCodes];
    }
}

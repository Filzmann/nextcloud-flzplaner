<?php

declare(strict_types=1);

$smoke = file_get_contents(__DIR__ . '/privacy-ddev-smoke.sh');
$probe = file_get_contents(__DIR__ . '/integration/PrivacyRuntimeProbe.php');
if ($smoke === false || $probe === false) {
    throw new RuntimeException('Der Datenschutz-DDEV-Smoke konnte nicht gelesen werden.');
}

foreach (['trap \'cleanup || report_failed_cleanup\' EXIT', 'user:disable', 'assert-note-absent', 'assert-clean'] as $contract) {
    if (!str_contains($smoke, $contract)) {
        throw new RuntimeException('Dem Datenschutz-DDEV-Smoke fehlt ein Sicherheitsvertrag: ' . $contract);
    }
}
foreach (['PARAM_INT_ARRAY', "'adp_shift_candidates'", "'adp_day_notes'", "'adp_shift_slots'", "'adp_month_plans'", "'adp_team_settings'"] as $contract) {
    if (!str_contains($probe, $contract)) {
        throw new RuntimeException('Der Datenbank-Probe fehlt ein Cleanup-Vertrag: ' . $contract);
    }
}
if (!str_contains($probe, "preg_match('/^P[0-9]{1,15}$/D'")) {
    throw new RuntimeException('Der Datenbank-Probe begrenzt Löschungen nicht auf synthetische Teamkürzel.');
}

echo 'AdPlaner privacy DDEV smoke contract test passed' . PHP_EOL;

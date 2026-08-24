<?php

declare(strict_types=1);

$shell = file_get_contents(__DIR__ . '/browser-ddev-smoke.sh');
$browser = file_get_contents(__DIR__ . '/js/browser-ddev-smoke.mjs');
$probe = file_get_contents(__DIR__ . '/integration/PrivacyRuntimeProbe.php');
if ($shell === false || $browser === false || $probe === false) {
    throw new RuntimeException('Der selbstbereinigende Browser-Smoke konnte nicht gelesen werden.');
}

foreach (['trap \'cleanup || report_failed_cleanup\' EXIT', 'run_probe assert-clean', 'user:delete', 'group:delete', 'rm -rf "$workdir"'] as $contract) {
    if (!str_contains($shell, $contract)) {
        throw new RuntimeException('Dem Browser-Smoke fehlt ein Cleanup-Vertrag: ' . $contract);
    }
}
foreach (['transition-status', 'add-self', 'save-note', 'settings-form', 'Page.captureScreenshot', '__adpBrowserErrors'] as $contract) {
    if (!str_contains($browser, $contract)) {
        throw new RuntimeException('Dem Browser-Smoke fehlt ein Oberflächenvertrag: ' . $contract);
    }
}
foreach (["['adp_shift_slots', ['team_code' => \$teamCode]]", "['adp_month_plans', ['team_code' => \$teamCode]]"] as $contract) {
    if (!str_contains($probe, $contract)) {
        throw new RuntimeException('Der Cleanup-Probe entfernt nicht alle durch initiale Browserloads erzeugten Testmonate.');
    }
}
if (str_contains($shell, 'app:disable') || str_contains($shell, 'app:enable')) {
    throw new RuntimeException('Die Browser-Abnahme darf den Nextcloud-App-Zustand nicht verändern.');
}

echo 'AdPlaner browser DDEV smoke contract test passed' . PHP_EOL;

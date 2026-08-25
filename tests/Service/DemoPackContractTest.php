<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/lib/Service/PlanerDemoPackService.php');
$command = file_get_contents($root . '/lib/Command/SeedDemoCommand.php');
$controller = file_get_contents($root . '/lib/Controller/DemoAdminController.php');
$routes = file_get_contents($root . '/appinfo/routes.php');
$info = file_get_contents($root . '/appinfo/info.xml');
$template = file_get_contents($root . '/templates/admin.php');
if (in_array(false, [$service, $command, $controller, $routes, $info, $template], true)) throw new RuntimeException('Planer-Demo-Pack ist unvollständig.');
foreach (['DemoAccountProvisioningService', "->provision('ad-suite-demo'", "['A', 'B', 'C']", 'TeamSettingsService', 'ShiftConfigService'] as $contract) if (!str_contains($service, $contract)) throw new RuntimeException("Planer-Demo-Vertrag fehlt: {$contract}");
foreach (['PlanerDemoPackService', '->install()'] as $contract) if (!str_contains($command, $contract)) throw new RuntimeException("Demo-Command delegiert nicht: {$contract}");
foreach (['/api/admin/demo-pack/install', "'verb' => 'POST'"] as $contract) if (!str_contains($routes, $contract)) throw new RuntimeException("Demo-Route fehlt: {$contract}");
foreach (['<command>OCA\\AdPlaner\\Command\\SeedDemoCommand</command>', '<admin>OCA\\AdPlaner\\Settings\\Admin</admin>', '<admin-section>OCA\\AdPlaner\\Settings\\AdminSection</admin-section>'] as $contract) if (!str_contains($info, $contract)) throw new RuntimeException("Admin-/Command-Registrierung fehlt: {$contract}");
foreach (['private function isAdmin()', '$this->groups->isAdmin(', 'temporaryAdminAccess->hasActiveGrant(', 'Http::STATUS_FORBIDDEN'] as $contract) if (!str_contains($controller, $contract)) throw new RuntimeException("Adminschutz fehlt: {$contract}");
if (str_contains($controller, 'NoCSRFRequired')) throw new RuntimeException('Demo-Installation umgeht CSRF.');
foreach (['id="adp-demo-confirm"', 'id="adp-demo-install"', 'Team A, Team B und Team C'] as $contract) if (!str_contains($template, $contract)) throw new RuntimeException("Demo-Adminoberfläche fehlt: {$contract}");

echo "DemoPackContractTest: OK\n";

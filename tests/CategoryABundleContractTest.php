<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$bundleRoot = $root . '/vendor/flz-shared';
$composerAutoload = $bundleRoot . '/php/vendor/autoload.php';
$lockPath = $bundleRoot . '/flz-shared.lock.json';

foreach ([$composerAutoload, $lockPath, $bundleRoot . '/php/src/Model/ModelApiTrait.php', $bundleRoot . '/php/src/Controller/ApiResponder.php', $bundleRoot . '/php/src/Service/AppLogger.php', $bundleRoot . '/php/src/Service/DemoAccountProvisioningService.php', $bundleRoot . '/LICENSE'] as $requiredPath) {
    if (!is_file($requiredPath)) {
        throw new RuntimeException('App-lokaler Kategorie-A-Bestandteil fehlt: ' . $requiredPath);
    }
}

require_once $composerAutoload;

$trait = 'OCA\\FlzPlaner\\Vendor\\Filzmann\\Shared\\Model\\ModelApiTrait';
if (!trait_exists($trait)) {
    throw new RuntimeException('Das app-isolierte ModelApiTrait ist nicht über Composer auflösbar.');
}

$lock = json_decode((string)file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
if (($lock['app_id'] ?? null) !== 'flzplaner'
    || ($lock['app_namespace'] ?? null) !== 'FlzPlaner'
    || ($lock['php_namespace'] ?? null) !== 'OCA\\FlzPlaner\\Vendor\\Filzmann\\Shared') {
    throw new RuntimeException('Der Kategorie-A-Lock beschreibt nicht das isolierte flzplaner-Bundle.');
}

$application = (string)file_get_contents($root . '/lib/AppInfo/Application.php');
if (!str_contains($application, "require_once dirname(__DIR__, 2) . '/vendor/flz-shared/php/vendor/autoload.php';")) {
    throw new RuntimeException('Das App-Bootstrap lädt den Kategorie-A-Autoloader nicht.');
}

echo "CategoryABundleContractTest: OK\n";

<?php

declare(strict_types=1);

$migration = __DIR__ . '/../lib/Migration/Version000003Date202608080001.php';
if (!is_file($migration)) {
    throw new RuntimeException('Die additive Monatsplanstatus-Migration fehlt.');
}

$source = file_get_contents($migration);
if ($source === false) {
    throw new RuntimeException('Die Monatsplanstatus-Migration konnte nicht gelesen werden.');
}

foreach (['adp_month_plans', 'team_code', 'plan_month', 'status', 'updated_by_uid', 'updated_at', 'adp_month_plan_unique'] as $contract) {
    if (!str_contains($source, $contract)) {
        throw new RuntimeException("Der Monatsplanstatus-Migrationsvertrag fehlt: {$contract}");
    }
}
if (!str_contains($source, "'default' => 'draft'")) {
    throw new RuntimeException('Bestehende Monatspläne erhalten keinen sicheren Entwurfsstatus.');
}

$revisionMigration = __DIR__ . '/../lib/Migration/Version000004Date202608090001.php';
if (!is_file($revisionMigration)) {
    throw new RuntimeException('Die additive Revisionsmigration für atomare Monatsänderungen fehlt.');
}
$revisionSource = file_get_contents($revisionMigration);
if ($revisionSource === false
    || !str_contains($revisionSource, "hasColumn('revision')")
    || !str_contains($revisionSource, "addColumn('revision', Types::INTEGER")
    || !str_contains($revisionSource, "'default' => 0")) {
    throw new RuntimeException('Bestehende Monatsstatuszeilen erhalten keine sichere Revisionsnummer ab 0.');
}

$info = simplexml_load_file(__DIR__ . '/../appinfo/info.xml');
if ($info === false || version_compare((string)$info->version, '0.4.0-rc.2', '<')) {
    throw new RuntimeException('Die additive Revisionsmigration besitzt keinen neuen App-Versionsauslöser.');
}

echo "AdPlaner month plan migration contract test passed\n";

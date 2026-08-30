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

$preferenceMigration = __DIR__ . '/../lib/Migration/Version000006Date202608300001.php';
if (!is_file($preferenceMigration)) {
    throw new RuntimeException('Die additive Migration für Schichtpräferenzen und persönliche Grenzen fehlt.');
}
$preferenceSource = (string)file_get_contents($preferenceMigration);
foreach (['preference', 'candidate_note', 'metadata_updated_at', 'adp_workload_limits', 'weekly_min', 'weekly_max', 'monthly_min', 'monthly_max', 'adp_workload_user_unique'] as $contract) {
    if (!str_contains($preferenceSource, $contract)) {
        throw new RuntimeException("Der Präferenz-/Auslastungs-Migrationsvertrag fehlt: {$contract}");
    }
}
if (!str_contains($preferenceSource, "'default' => 'neutral'")) {
    throw new RuntimeException('Bestehende Schichtwünsche müssen neutral bleiben.');
}
if ($info === false || version_compare((string)$info->version, '0.6.0-rc.1', '<')) {
    throw new RuntimeException('Die neue Migration besitzt keinen App-Versionsauslöser.');
}

$fixedMigration = __DIR__ . '/../lib/Migration/Version000007Date202608300002.php';
if (!is_file($fixedMigration)) throw new RuntimeException('Die additive Festschichtmigration fehlt.');
$fixedSource = (string)file_get_contents($fixedMigration);
foreach (['assignment_source','fixed_deleted','adp_regular_shifts','weekday','segment_key','adp_regular_shift_unique','adp_fixed_conflicts','reported_by_uid','resolved_by_uid','adp_fixed_conflict_slot_unique'] as $contract) {
    if (!str_contains($fixedSource,$contract)) throw new RuntimeException('Der Festschicht-Migrationsvertrag fehlt: '.$contract);
}
if (!str_contains($fixedSource,"'default'=>'manual'")) throw new RuntimeException('Bestehende Kandidaturen müssen manuell bleiben.');
if ($info === false || version_compare((string)$info->version,'0.7.0-rc.1','<')) throw new RuntimeException('Die Festschichtmigration besitzt keinen neuen App-Versionsauslöser.');
$overrideMigration=__DIR__.'/../lib/Migration/Version000008Date202608300003.php';
if(!is_file($overrideMigration)||!str_contains((string)file_get_contents($overrideMigration),'fixed_modified')) throw new RuntimeException('Die additive Migration für individuell aufgelöste Festschichten fehlt.');
if($info===false||version_compare((string)$info->version,'0.7.0-rc.2','<')) throw new RuntimeException('Die Auflösungsmigration besitzt keinen neuen App-Versionsauslöser.');

echo "AdPlaner month plan migration contract test passed\n";

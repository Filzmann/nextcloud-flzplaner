<?php

declare(strict_types=1);

use OCA\FlzPlaner\Service\TeamBureauAssignmentPolicy;

require_once __DIR__ . '/../bootstrap.php';

$policy = new TeamBureauAssignmentPolicy();

if (!$policy->mayAssign(
    ['flz-EB', 'flz-ASN-TeamA', 'flz-Bereich-West'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-BL', 'flz-StvBL'],
    'flz-Bereich-West',
    null,
)) {
    throw new RuntimeException('Die zuständige EB im Zielbüro muss die Erstzuordnung setzen dürfen.');
}
if ($policy->mayAssign(
    ['flz-EB', 'flz-ASN-TeamA', 'flz-Bereich-Nordost'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-BL', 'flz-StvBL'],
    'flz-Bereich-West',
    null,
)) {
    throw new RuntimeException('Eine EB außerhalb des Zielbüros darf keine Zuordnung setzen.');
}
if (!$policy->mayAssign(
    ['flz-BL', 'flz-Bereich-West'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-BL', 'flz-StvBL'],
    'flz-Bereich-West',
    'flz-Bereich-West',
)) {
    throw new RuntimeException('Die Büroleitung des betroffenen Büros muss die Zuordnung ändern dürfen.');
}
if ($policy->mayAssign(
    ['flz-BL', 'flz-Bereich-West'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-BL', 'flz-StvBL'],
    'flz-Bereich-Nordost',
    'flz-Bereich-West',
)) {
    throw new RuntimeException('Eine Leitung ohne Zuständigkeit für beide betroffenen Büros darf nicht umhängen.');
}

echo "FlzPlaner team bureau assignment policy tests passed\n";

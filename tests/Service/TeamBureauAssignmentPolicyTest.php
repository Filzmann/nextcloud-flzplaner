<?php

declare(strict_types=1);

use OCA\FlzPlaner\Service\TeamBureauAssignmentPolicy;

require_once __DIR__ . '/../bootstrap.php';

$policy = new TeamBureauAssignmentPolicy();

if (!$policy->mayAssign(
    ['flz-EB', 'flz-ASN-TeamA', 'flz-Bereich-West'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-Bereich-Nordost', 'flz-Bereich-West', 'flz-Bereich-Sued'],
    'flz-Bereich-West',
)) {
    throw new RuntimeException('Die zuständige EB im Zielbüro muss die Erstzuordnung setzen dürfen.');
}
if ($policy->mayAssign(
    ['flz-EB', 'flz-ASN-TeamA', 'flz-Bereich-Nordost'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-Bereich-Nordost', 'flz-Bereich-West', 'flz-Bereich-Sued'],
    'flz-Bereich-West',
)) {
    throw new RuntimeException('Eine EB außerhalb des Zielbüros darf keine Zuordnung setzen.');
}
if ($policy->mayAssign(
    ['flz-BL', 'flz-Bereich-West'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-Bereich-Nordost', 'flz-Bereich-West', 'flz-Bereich-Sued'],
    'flz-Bereich-West',
)) {
    throw new RuntimeException('Büroleitungen dürfen die EB-eigene Team-Bürozuordnung nicht übernehmen.');
}
if ($policy->mayAssign(
    ['flz-EB', 'flz-ASN-TeamA', 'flz-Bereich-West', 'flz-Bereich-Sued'],
    'flz-ASN-TeamA',
    'flz-EB',
    ['flz-Bereich-Nordost', 'flz-Bereich-West', 'flz-Bereich-Sued'],
    'flz-Bereich-Nordost',
)) {
    throw new RuntimeException('Eine EB mit mehreren Büros darf keine Team-Bürozuordnung setzen.');
}

echo "FlzPlaner team bureau assignment policy tests passed\n";

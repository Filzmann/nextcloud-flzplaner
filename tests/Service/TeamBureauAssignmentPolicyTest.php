<?php

declare(strict_types=1);

use OCA\FlzPlaner\Service\TeamBureauAssignmentPolicy;

require_once __DIR__ . '/../bootstrap.php';

$policy = new TeamBureauAssignmentPolicy();

if (!$policy->mayAssign(
    ['EB', 'ASN-TeamA', 'Bereich-West'],
    'ASN-TeamA',
    'EB',
    ['Bereich-Nordost', 'Bereich-West', 'Bereich-Sued'],
    'Bereich-West',
)) {
    throw new RuntimeException('Die zuständige EB im Zielbüro muss die Erstzuordnung setzen dürfen.');
}
if ($policy->mayAssign(
    ['EB', 'ASN-TeamA', 'Bereich-Nordost'],
    'ASN-TeamA',
    'EB',
    ['Bereich-Nordost', 'Bereich-West', 'Bereich-Sued'],
    'Bereich-West',
)) {
    throw new RuntimeException('Eine EB außerhalb des Zielbüros darf keine Zuordnung setzen.');
}
if ($policy->mayAssign(
    ['BL', 'Bereich-West'],
    'ASN-TeamA',
    'EB',
    ['Bereich-Nordost', 'Bereich-West', 'Bereich-Sued'],
    'Bereich-West',
)) {
    throw new RuntimeException('Büroleitungen dürfen die EB-eigene Team-Bürozuordnung nicht übernehmen.');
}
if ($policy->mayAssign(
    ['EB', 'ASN-TeamA', 'Bereich-West', 'Bereich-Sued'],
    'ASN-TeamA',
    'EB',
    ['Bereich-Nordost', 'Bereich-West', 'Bereich-Sued'],
    'Bereich-Nordost',
)) {
    throw new RuntimeException('Eine EB mit mehreren Büros darf keine Team-Bürozuordnung setzen.');
}

echo "FlzPlaner team bureau assignment policy tests passed\n";

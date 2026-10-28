<?php

declare(strict_types=1);

use OCA\FlzPlaner\PublicApi\V1\AcuteShiftTeam;

require_once __DIR__ . '/../bootstrap.php';

$team = new AcuteShiftTeam(
    'TeamA',
    'Assistenzteam A',
    'west',
    [['key' => 'day', 'label' => 'Tag', 'startsAt' => '08:00', 'endsAt' => '20:00']],
    [['uid' => 'assistenz-a', 'displayName' => 'Assistenz A']],
);

if ($team->contractVersion() !== '1.0' || $team->bureauKey() !== 'west') {
    throw new RuntimeException('Teamcode und genau ein Büro müssen Teil des öffentlichen Vertrags sein.');
}
if ($team->shiftTemplates()[0]['startsAt'] !== '08:00' || $team->assistants()[0]['uid'] !== 'assistenz-a') {
    throw new RuntimeException('Schichtvorschläge und Team-AS fehlen im öffentlichen Vertrag.');
}

try {
    new AcuteShiftTeam('TeamA', 'Assistenzteam A', '', [], []);
    throw new RuntimeException('Ein Team ohne Büro darf nicht als vermittelbar veröffentlicht werden.');
} catch (InvalidArgumentException) {
}

echo "FlzPlaner acute shift catalog contract tests passed\n";

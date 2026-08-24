<?php

declare(strict_types=1);

$smoke = file_get_contents(__DIR__ . '/access-http-smoke.sh');
if ($smoke === false) {
    throw new RuntimeException('Der C3-HTTP-Smoke konnte nicht gelesen werden.');
}
if (!str_contains($smoke, '$candidate["uid"] ?? ""')) {
    throw new RuntimeException('Der C3-HTTP-Smoke prüft nicht den öffentlichen Kandidaten-Schlüssel uid.');
}
if (str_contains($smoke, '$candidate["assistantUid"]')) {
    throw new RuntimeException('Der C3-HTTP-Smoke verwendet noch den internen Kandidaten-Schlüssel assistantUid.');
}
foreach (['$beforeCandidateUids', '$afterCandidateUids', '$beforeCandidateUids !== $afterCandidateUids'] as $contract) {
    if (!str_contains($smoke, $contract)) {
        throw new RuntimeException('Der C3-HTTP-Smoke vergleicht den Schichtzustand vor und nach der abgewiesenen Änderung nicht korrekt.');
    }
}
if (str_contains($smoke, 'Die abgewiesene Fremdänderung wurde dennoch gespeichert.')) {
    throw new RuntimeException('Der C3-HTTP-Smoke verwechselt einen bereits vorhandenen Fremdeintrag mit einer neuen Mutation.');
}

echo 'AdPlaner access smoke contract test passed' . PHP_EOL;

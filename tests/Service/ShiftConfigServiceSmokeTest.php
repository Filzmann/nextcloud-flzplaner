<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use OCA\AdPlaner\Service\ShiftConfigService;
use function OCA\AdPlaner\Tests\assertSameValue;

$service = new ShiftConfigService();
$defaults = $service->defaults();
$defaultSegments = $service->segments($defaults);
$defaultModels = \OCA\AdPlaner\Model\ShiftDefinition::get_all($defaultSegments);

assertSameValue(['early', 'late', 'night'], array_column($defaultSegments, 'key'), 'Default shifts should use the normal three-shift setup.');
assertSameValue(3, count($defaultModels), 'ShiftDefinition::get_all should hydrate API lists.');
assertSameValue('08:00', $defaultSegments[0]['startsAt'], 'Default early shift should start at 08:00.');
assertSameValue('14:00', $defaultSegments[0]['endsAt'], 'Default early shift should end at 14:00.');
assertSameValue('20:00', $defaultSegments[2]['startsAt'], 'Default night shift should start at 20:00.');
assertSameValue('08:00', $defaultSegments[2]['endsAt'], 'Default night shift should end at the next early start.');

$customSettings = $service->normalize([
    'meetingDay' => '2026-07-15',
    'shifts' => [
        [
            'key' => 'first',
            'label' => 'Erste',
            'startsAt' => '08:00',
            'endsAt' => '12:00',
            'enabled' => true,
        ],
        [
            'key' => 'overlap',
            'label' => 'Überlappung',
            'startsAt' => '11:00',
            'endsAt' => '15:00',
            'enabled' => true,
        ],
        [
            'key' => 'night',
            'label' => 'Nacht',
            'startsAt' => '20:00',
            'endsAt' => '08:00',
            'enabled' => false,
        ],
    ],
]);
$customSegments = $service->segments($customSettings);
$days = $service->monthDays('2026-02');
$leapDays = $service->monthDays('2028-02');

assertSameValue('2026-07-15', $customSettings['meetingDay'], 'Meeting day should be preserved.');
assertSameValue(['first', 'overlap', 'night'], array_column($customSegments, 'key'), 'Custom shifts should keep their configured order.');
assertSameValue('11:00', $customSegments[1]['startsAt'], 'Overlapping custom shifts should be allowed.');
assertSameValue('15:00', $customSegments[1]['endsAt'], 'Overlapping custom shifts should keep their end time.');
assertSameValue('20:00', $customSegments[2]['startsAt'], 'Cross-midnight custom shifts should keep their start time.');
assertSameValue('08:00', $customSegments[2]['endsAt'], 'Cross-midnight custom shifts should keep their end time.');
assertSameValue(false, $customSegments[2]['enabled'], 'Disabled custom shifts should be preserved.');
assertSameValue(28, count($days), 'February 2026 should have 28 days.');
assertSameValue('2026-02-01', $days[0]['date'], 'First month day should be correct.');
assertSameValue(29, count($leapDays), 'A leap-year February should have 29 days.');
assertSameValue('2028-02-29', $leapDays[28]['date'] ?? null, 'The leap day should occur exactly once at the end of February 2028.');

$gappedSegments = $service->segments($service->normalize(['shifts' => [
    ['key' => 'morning', 'label' => 'Vormittag', 'startsAt' => '08:00', 'endsAt' => '10:00', 'enabled' => true],
    ['key' => 'afternoon', 'label' => 'Nachmittag', 'startsAt' => '12:00', 'endsAt' => '14:00', 'enabled' => true],
]]));
assertSameValue(
    [['08:00', '10:00'], ['12:00', '14:00']],
    array_map(static fn(array $shift): array => [$shift['startsAt'], $shift['endsAt']], $gappedSegments),
    'A deliberate gap between shifts should remain valid and must not be closed automatically.'
);

$unicodeBoundary = $service->normalize(['shifts' => [[
    'key' => 'unicode',
    'label' => str_repeat('Ä', 64),
    'startsAt' => '08:00',
    'endsAt' => '09:00',
    'enabled' => true,
]]]);
assertSameValue(str_repeat('Ä', 64), $unicodeBoundary['shifts'][0]['label'], 'A 64-character Unicode shift label should be valid.');

$assertInvalidArgument = static function (callable $operation, string $message): void {
    try {
        $operation();
    } catch (\InvalidArgumentException) {
        return;
    }

    throw new \RuntimeException($message);
};
$assertInvalidArgument(
    static fn() => $service->normalize(['shifts' => 'invalid']),
    'Non-list shift settings should be rejected.'
);
$assertInvalidArgument(
    static fn() => $service->normalize(['shifts' => [
        'named-shift' => [
            'key' => 'day',
            'label' => 'Tag',
            'startsAt' => '08:00',
            'endsAt' => '16:00',
            'enabled' => true,
        ],
    ]]),
    'JSON objects must not be silently reinterpreted as ordered shift lists.'
);
$assertInvalidArgument(
    static fn() => $service->monthDays('2026-13'),
    'Out-of-range months should be rejected.'
);
$assertInvalidArgument(
    static fn() => $service->normalizeDate('2026-02-30'),
    'Impossible calendar dates should be rejected.'
);
$assertInvalidArgument(
    static fn() => $service->normalizeDate('30.02.2026'),
    'Non-ISO calendar dates should be rejected.'
);
$assertInvalidArgument(
    static fn() => $service->normalize(['shifts' => [[
        'key' => 'blank-label',
        'label' => '   ',
        'startsAt' => '08:00',
        'endsAt' => '09:00',
        'enabled' => true,
    ]]]),
    'A blank shift label must be rejected instead of silently replaced.'
);
$assertInvalidArgument(
    static fn() => $service->normalize(['shifts' => [[
        'key' => 'too-long',
        'label' => str_repeat('Ä', 65),
        'startsAt' => '08:00',
        'endsAt' => '09:00',
        'enabled' => true,
    ]]]),
    'A Unicode shift label longer than 64 characters should be rejected.'
);
$assertInvalidArgument(
    static fn() => $service->normalize(['shifts' => [[
        'key' => 'invalid-utf8',
        'label' => "\xC3\x28",
        'startsAt' => '08:00',
        'endsAt' => '09:00',
        'enabled' => true,
    ]]]),
    'An invalid UTF-8 shift label should be rejected.'
);
$invalidEnabledShift = static fn(mixed $enabled): array => ['shifts' => [[
    'key' => 'typed-enabled',
    'label' => 'Typisiert',
    'startsAt' => '08:00',
    'endsAt' => '09:00',
    'enabled' => $enabled,
]]];
$assertInvalidArgument(
    static fn() => $service->normalize($invalidEnabledShift('irgendwie')),
    'Unknown enabled strings must not silently disable a shift.'
);
$assertInvalidArgument(
    static fn() => $service->normalize($invalidEnabledShift([])),
    'Structured enabled values must be rejected instead of coerced.'
);
$assertInvalidArgument(
    static fn() => $service->normalize($invalidEnabledShift(2)),
    'Enabled integers other than zero and one must be rejected.'
);
assertSameValue(false, $service->normalize($invalidEnabledShift('false'))['shifts'][0]['enabled'], 'The explicit false string remains supported.');

echo 'AdPlaner shift config smoke tests passed' . PHP_EOL;

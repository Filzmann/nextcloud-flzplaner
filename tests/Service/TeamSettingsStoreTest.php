<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use OCA\AdPlaner\Repository\TeamSettingsRepository;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\AdPlaner\Store\TeamSettingsStore;
use function OCA\AdPlaner\Tests\assertSameValue;

final class TeamSettingsRepositoryFake extends TeamSettingsRepository {
    public array $saved = [];
    public ?array $row = null;

    public function __construct() {}

    public function findByCode(string $teamCode): ?array {
        return $this->row;
    }

    public function save(string $teamCode, string $displayName, array $settings): void {
        $this->saved[] = compact('teamCode', 'displayName', 'settings');
    }
}

$repository = new TeamSettingsRepositoryFake();
$store = new TeamSettingsStore($repository, new ShiftConfigService());
$config = ['shifts' => [[
    'key' => 'day',
    'label' => 'Tag',
    'startsAt' => '08:00',
    'endsAt' => '16:00',
    'enabled' => true,
]]];

$validName = str_repeat('Ä', 255);
$saved = $store->save('A1', $validName, $config);
assertSameValue($validName, $saved->displayName, 'A 255-character Unicode team display name should be valid.');
assertSameValue(1, count($repository->saved), 'A valid team display name should be persisted once.');

$assertRejectedWithoutWrite = static function (string $displayName, string $message) use ($store, $repository, $config): void {
    $before = count($repository->saved);
    try {
        $store->save('A1', $displayName, $config);
    } catch (\InvalidArgumentException) {
        assertSameValue($before, count($repository->saved), $message);
        return;
    }

    throw new \RuntimeException($message);
};

$assertRejectedWithoutWrite(str_repeat('Ä', 256), 'A team display name longer than 255 characters must be rejected without persistence.');
$assertRejectedWithoutWrite("\xC3\x28", 'An invalid UTF-8 team display name must be rejected without persistence.');
$assertRejectedWithoutWrite('   ', 'A blank team display name must be rejected without persistence.');

$repository->row = [
    'display_name' => '0',
    'settings_json' => json_encode($config, JSON_THROW_ON_ERROR),
];
assertSameValue('0', $store->forTeam('A1')->displayName, 'The valid display name "0" must survive the persistence roundtrip.');

echo 'AdPlaner team settings store tests passed' . PHP_EOL;

<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$bundleRoot = $root . '/js/shared';
$violations = [];

foreach (['api-client.js', 'model.js', 'overlay.js', 'repository.js', 'ui.js', 'LICENSE', 'flz-shared.lock.json'] as $asset) {
    if (!is_file($bundleRoot . '/' . $asset)) {
        $violations[] = 'js/shared/' . $asset . ': app-lokales Kategorie-A-Artefakt fehlt';
    }
}

$lockPath = $bundleRoot . '/flz-shared.lock.json';
if (is_file($lockPath)) {
    $lock = json_decode((string)file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
    if (($lock['schema'] ?? null) !== 1
        || ($lock['component'] ?? null) !== 'browser'
        || ($lock['app_id'] ?? null) !== 'flzplaner'
        || ($lock['app_namespace'] ?? null) !== 'FlzPlaner'
        || ($lock['browser_global'] ?? null) !== 'window.OCA.FlzPlaner.Shared'
        || ($lock['packages']['browser']['name'] ?? null) !== '@filzmann/shared-browser'
        || ($lock['packages']['browser']['version'] ?? null) !== '0.1.0'
        || !preg_match('/^[a-f0-9]{64}$/', (string)($lock['packages']['browser']['sha256'] ?? ''))
        || array_keys($lock['packages'] ?? []) !== ['browser']) {
        $violations[] = 'js/shared/flz-shared.lock.json: Browser-Paket, Zielglobal oder Quelldigest ist inkohärent';
    }
}

$licensePath = $bundleRoot . '/LICENSE';
if (is_file($licensePath)) {
    $license = (string)file_get_contents($licensePath);
    if (!str_contains($license, 'SPDX-License-Identifier: AGPL-3.0-or-later')
        || !str_contains($license, 'GNU Affero General Public License')) {
        $violations[] = 'js/shared/LICENSE: gebündelte Lizenzmetadaten fehlen';
    }
}

$indexTemplate = (string)file_get_contents($root . '/templates/index.php');
$adminTemplate = (string)file_get_contents($root . '/templates/admin.php');
foreach (['api-client', 'model', 'overlay', 'repository', 'ui'] as $asset) {
    if (!str_contains($indexTemplate, "addScript('flzplaner', 'shared/{$asset}')")) {
        $violations[] = "templates/index.php: shared/{$asset} wird nicht app-lokal geladen";
    }
}
if (!str_contains($adminTemplate, "addScript('flzplaner', 'shared/api-client')")) {
    $violations[] = 'templates/admin.php: shared/api-client wird nicht app-lokal geladen';
}

$loadOrder = [
    'shared/api-client' => 'admin-access',
    'shared/overlay' => 'admin-access',
    'shared/ui' => 'modules/ui',
    'shared/model' => 'models/assistant',
    'shared/repository' => 'repositories/plan-repository',
];
foreach ($loadOrder as $sharedAsset => $firstConsumer) {
    $sharedPosition = strpos($indexTemplate, $sharedAsset);
    $consumerPosition = strpos($indexTemplate, $firstConsumer);
    if ($sharedPosition === false || $consumerPosition === false || $sharedPosition > $consumerPosition) {
        $violations[] = "templates/index.php: {$sharedAsset} wird nicht vor {$firstConsumer} geladen";
    }
}
if (strpos($adminTemplate, 'shared/api-client') > strpos($adminTemplate, "addScript('flzplaner', 'admin')")) {
    $violations[] = 'templates/admin.php: shared/api-client wird nicht vor admin geladen';
}

foreach ([$root . '/js', $root . '/templates'] as $sourceRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $contents = (string)file_get_contents($file->getPathname());
        foreach ([
            'window.LocalBase.api',
            'window.LocalBase.models',
            'window.LocalBase.repositories',
            'window.LocalBase.ui',
            "addScript('localbase', 'api/",
            "addScript('localbase','api/",
            "addScript('localbase', 'models/",
            "addScript('localbase','models/",
            "addScript('localbase', 'repositories/",
            "addScript('localbase','repositories/",
            "addScript('localbase', 'ui/",
            "addScript('localbase','ui/",
            '/../flz_shared/',
            '/../../flz_shared/',
        ] as $forbidden) {
            if (str_contains($contents, $forbidden)) {
                $violations[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $forbidden;
            }
        }
    }
}

if ($violations !== []) {
    throw new RuntimeException(
        "FlzPlaner besitzt keinen isolierten Kategorie-A-Browservertrag:\n- "
        . implode("\n- ", array_unique($violations)),
    );
}

echo "CategoryABrowserBundleContractTest: OK\n";

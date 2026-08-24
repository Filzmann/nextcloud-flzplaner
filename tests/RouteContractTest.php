<?php

declare(strict_types=1);

$configuration = require dirname(__DIR__) . '/appinfo/routes.php';
$routes = $configuration['routes'] ?? [];
$byName = [];
foreach ($routes as $route) {
    $byName[$route['name'] ?? ''] = $route;
}

foreach (['api#addShiftCandidate', 'api#removeShiftCandidate'] as $name) {
    $slotRequirement = $byName[$name]['requirements']['slotId'] ?? null;
    if ($slotRequirement !== '\\d+') {
        throw new RuntimeException($name . ' muss nichtnumerische Schicht-IDs bereits an der Route abweisen.');
    }
}

echo 'AdPlaner route contract tests passed' . PHP_EOL;

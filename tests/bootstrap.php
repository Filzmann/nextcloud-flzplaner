<?php

declare(strict_types=1);

$workspaceRoot = dirname(__DIR__, 2);
$appRoot = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($workspaceRoot, $appRoot): void {
    $prefixes = [
        'OCA\\LocalBase\\Tests\\Support\\' => $workspaceRoot . '/localbase/tests/Support/',
        'OCA\\LocalBase\\' => $workspaceRoot . '/localbase/lib/',
        'OCA\\AdPlaner\\' => $appRoot . '/lib/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

require_once $workspaceRoot . '/localbase/tests/Support/assertions.php';
require_once __DIR__ . '/Service/helpers.php';

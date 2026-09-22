<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$technicalAdminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$template = (string)file_get_contents($root . '/templates/index.php');
$script = is_file($root . '/js/admin-access.js') ? (string)file_get_contents($root . '/js/admin-access.js') : '';
$routes = (string)file_get_contents($root . '/appinfo/routes.php');
$migration = (string)file_get_contents($root . '/lib/Migration/Version000005Date202608250001.php');
$service = (string)file_get_contents($root . '/lib/Service/TemporaryAdminAccessService.php');
$pageController = (string)file_get_contents($root . '/lib/Controller/PageController.php');
$accessController = (string)file_get_contents($root . '/lib/Controller/TemporaryAdminAccessController.php');

foreach (['adp-full-access-form', 'adp-full-access-enabled', 'adp-full-access-history', 'value="1440"'] as $value) {
    if (!str_contains($template, $value)) throw new RuntimeException('DPO-Freigabesteuerung fehlt: ' . $value);
}
foreach (['Datenschutzbeauftragte', 'Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff', 'href="#adp-full-access"'] as $value) {
    if (!str_contains($template, $value)) throw new RuntimeException('Sichere rollenabhängige Eintrittsmeldung fehlt: ' . $value);
}
foreach (['/api/admin/full-access', 'durationMinutes', 'targetUid', 'Widerrufen'] as $value) {
    if (!str_contains($script . $routes, $value)) throw new RuntimeException('Adminfreigabe-API fehlt: ' . $value);
}
foreach (['adp_admin_access', 'target_uid', 'granted_by', 'starts_at', 'ends_at', 'revoked_at', 'revoked_by', 'created_at'] as $value) {
    if (!str_contains($migration, $value)) throw new RuntimeException('Adminfreigabe-Migration fehlt: ' . $value);
}
if (str_contains($technicalAdminTemplate, 'adp-full-access-form')) throw new RuntimeException('Freigabesteuerung ist noch an den technischen Adminbereich gebunden.');
foreach (['canManageAdminAccess', 'showMissingAdminGrant', 'showAdminAccessLink'] as $value) {
    if (!str_contains($template, $value) || !str_contains($pageController, "'{$value}'")) throw new RuntimeException('Rollenabhängige Eintrittsgrenze fehlt: ' . $value);
}
if (!str_contains($pageController, "'showAdminAccessLink' => \$canManageAdminAccess && \$showMissingAdminGrant")) throw new RuntimeException('Direktlink ist nicht auf gleichzeitige Datenschutz- und Adminrolle begrenzt.');
if (str_contains($accessController, 'PublicPage')) throw new RuntimeException('Freigaberouten dürfen nicht öffentlich erreichbar sein.');

echo "AdPlaner admin full access contract tests passed\n";

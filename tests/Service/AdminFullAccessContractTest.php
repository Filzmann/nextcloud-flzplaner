<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$technicalAdminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$template = (string)file_get_contents($root . '/templates/index.php');
$script = is_file($root . '/js/admin-access.js') ? (string)file_get_contents($root . '/js/admin-access.js') : '';
$bundleOverlay = is_file($root . '/js/shared/overlay.js') ? (string)file_get_contents($root . '/js/shared/overlay.js') : '';
$routes = (string)file_get_contents($root . '/appinfo/routes.php');
$migration = (string)file_get_contents($root . '/lib/Migration/Version000005Date202608250001.php');
$service = (string)file_get_contents($root . '/lib/Service/TemporaryAdminAccessService.php');
$pageController = (string)file_get_contents($root . '/lib/Controller/PageController.php');
$accessController = (string)file_get_contents($root . '/lib/Controller/TemporaryAdminAccessController.php');

foreach (['flz-planer-full-access-form', 'flz-planer-full-access-enabled', 'flz-planer-full-access-history', 'value="1440"'] as $value) {
    if (!str_contains($template, $value)) throw new RuntimeException('Freigabesteuerung fehlt: ' . $value);
}
foreach (['Datenschutzbeauftragte', 'zuständige Einsatzbegleitungen', 'Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff'] as $value) {
    if (!str_contains($template, $value)) throw new RuntimeException('Sichere rollenabhängige Eintrittsmeldung fehlt: ' . $value);
}

// Overlay-Vertrag: Das Warn-Icon am App-Titel ist ein Button, der ein modales Overlay öffnet.
$overlayId = 'flz-planer-admin-access-overlay';
foreach ([
    'id="' . $overlayId . '"',
    'role="dialog"',
    'aria-modal="true"',
    'flz-planer-admin-access-overlay-close',
    'id="flz-planer-admin-access-warning"',
    'aria-haspopup="dialog"',
    'aria-controls="' . $overlayId . '"',
    'aria-expanded',
] as $value) {
    if (!str_contains($template, $value)) throw new RuntimeException('Overlay-Vertrag fehlt in der Vorlage: ' . $value);
}
if (str_contains($template, '<details class="flz-planer-admin-access-warning"')) throw new RuntimeException('Warn-Icon ist noch als aufklappbares details-Element umgesetzt.');
if (str_contains($template, 'target="_blank"')) throw new RuntimeException('Veralteter Verweis "Freigabesteuerung in neuem Tab öffnen" ist noch vorhanden.');
$overlayPos = strpos($template, 'id="' . $overlayId . '"');
$sectionPos = strpos($template, 'id="flz-planer-full-access"');
if ($overlayPos === false || $sectionPos === false || $overlayPos >= $sectionPos) throw new RuntimeException('Freigabesteuerung liegt nicht innerhalb des Overlays.');
foreach (['Kein fachlicher Admin-Vollzugriff aktiv.'] as $value) {
    $pos = strpos($template, $value);
    if ($pos === false || $pos < $overlayPos) throw new RuntimeException('Warntext liegt nicht im Overlay: ' . $value);
}

foreach (['/api/admin/full-access', 'durationMinutes', 'targetUid', $overlayId] as $value) {
    if (!str_contains($script . $routes, $value)) throw new RuntimeException('Adminfreigabe-UI/API-Vertrag fehlt: ' . $value);
}
foreach (['AdminAccessOverlay', 'Overlay', 'Escape', 'aria-expanded', 'flz-shared-overlay', 'flz-shared-warn-trigger', 'Widerrufen', 'hidden'] as $value) {
    if (!str_contains($bundleOverlay, $value)) throw new RuntimeException('Das geteilte Overlay-Bundle fehlt: ' . $value);
}
if (!str_contains($script, 'AdminAccessOverlay')) throw new RuntimeException('admin-access.js bootstrapped nicht das geteilte AdminAccessOverlay.');
foreach (["addScript('flzplaner', 'shared/overlay')", 'class="flz-shared-overlay"', 'class="flz-shared-warn-trigger"'] as $value) {
    if (!str_contains($template, $value)) throw new RuntimeException('Overlay-Vertrag fehlt in der Vorlage: ' . $value);
}
foreach (['flz_planer_admin_access', 'target_uid', 'granted_by', 'starts_at', 'ends_at', 'revoked_at', 'revoked_by', 'created_at'] as $value) {
    if (!str_contains($migration, $value)) throw new RuntimeException('Adminfreigabe-Migration fehlt: ' . $value);
}
if (str_contains($technicalAdminTemplate, 'flz-planer-full-access-form')) throw new RuntimeException('Freigabesteuerung ist noch an den technischen Adminbereich gebunden.');
foreach (['canManageAdminAccess', 'showMissingAdminGrant'] as $value) {
    if (!str_contains($template, $value) || !str_contains($pageController, "'{$value}'")) throw new RuntimeException('Rollenabhängige Eintrittsgrenze fehlt: ' . $value);
}
if (!str_contains($pageController, "'showAdminAccessLink'")) throw new RuntimeException('Rollenabhängige Eintrittsgrenze fehlt: showAdminAccessLink');
if (!str_contains($pageController, "'showAdminAccessLink' => \$canManageAdminAccess && \$showMissingAdminGrant")) throw new RuntimeException('Direktlink ist nicht auf gleichzeitige Datenschutz- und Adminrolle begrenzt.');
if (str_contains($accessController, 'PublicPage')) throw new RuntimeException('Freigaberouten dürfen nicht öffentlich erreichbar sein.');
foreach (['PlanerPermissionPolicy', 'PlanerPermissionSourceInterface', 'mayManageTemporaryAdminGrants'] as $value) {
    if (!str_contains($service, $value)) throw new RuntimeException('Serverseitige DPO-/Team-EB-Grenze fehlt: ' . $value);
}

echo "FlzPlaner admin full access overlay contract tests passed\n";

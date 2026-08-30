<?php

declare(strict_types=1);

$css = file_get_contents(__DIR__ . '/../../css/style.css');
$template = file_get_contents(__DIR__ . '/../../templates/index.php');
if ($css === false || $template === false) {
    throw new RuntimeException('AdPlaner-Layoutquellen konnten nicht gelesen werden.');
}

foreach (['max-height: calc(100vh - 260px)', '.adp-month-table th:last-child', '.adp-month-table td:last-child', 'position: sticky', 'right: 0'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der sichtbare Monatsplan-Scrollvertrag fehlt: {$contract}");
    }
}
foreach (['.adp-assignment-control', 'position: relative', '.adp-assignment-picker[hidden]', 'display: none', 'position: absolute'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der kompakte Zuteilungsdialog fehlt: {$contract}");
    }
}
foreach (['.adp-chip--editable:hover > .adp-preference-panel', '.adp-chip--editable:focus-within > .adp-preference-panel', '.adp-preference-marker--favorite', '.adp-preference-option--favorite > span', 'color: #f5b301', '.adp-shift-notes'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der zugängliche Schichtpräferenz-Popoververtrag fehlt: {$contract}");
    }
}
foreach (['.adp-shift-note-editor', '.adp-shift-note-actions', '.adp-shift-note-editor[hidden]', 'display: none'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der horizontale Anmerkungs- und Editorvertrag fehlt: {$contract}");
    }
}
foreach (['id="month-prev"', 'id="month-next"', 'aria-label="Vorheriger Monat"', 'aria-label="Nächster Monat"', 'min="2000-01"', 'max="2100-12"', 'required'] as $contract) {
    if (!str_contains($template, $contract)) {
        throw new RuntimeException("Die direkte Monatsnavigation fehlt: {$contract}");
    }
}
foreach (['id="adp-tab-workload"', 'data-view="workload"', '>Auslastung</button>', 'aria-controls="adp-workload-overlay"', 'aria-haspopup="dialog"', 'id="adp-workload-overlay"', 'role="dialog"'] as $contract) {
    if (!str_contains($template, $contract)) {
        throw new RuntimeException("Der Auslastungstab fehlt: {$contract}");
    }
}
foreach (['.adp-tab-area', '.adp-workload-overlay', 'top: calc(100% + 6px)', 'z-index: 120', '.adp-week-label'] as $contract) {
    if (!str_contains($css,$contract)) throw new RuntimeException("Der verankerte Auslastungs-Overlayvertrag fehlt: {$contract}");
}
foreach (['.adp-capacity--under', '.adp-capacity--within', '.adp-capacity--over', '#dcfce7', '#fee2e2', '.adp-plan-proposal'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Die kompakte Auslastungsampel fehlt: {$contract}");
    }
}

echo "AdPlaner layout smoke test passed\n";

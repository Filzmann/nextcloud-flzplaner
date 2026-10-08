<?php

declare(strict_types=1);

$css = file_get_contents(__DIR__ . '/../../css/style.css');
$template = file_get_contents(__DIR__ . '/../../templates/index.php');
if ($css === false || $template === false) {
    throw new RuntimeException('FlzPlaner-Layoutquellen konnten nicht gelesen werden.');
}

foreach (['max-height: calc(100vh - 260px)', '.flz-planer-month-table th:last-child', '.flz-planer-month-table td:last-child', 'position: sticky', 'right: 0'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der sichtbare Monatsplan-Scrollvertrag fehlt: {$contract}");
    }
}
foreach (['.flz-planer-assignment-control', 'position: relative', '.flz-planer-assignment-picker[hidden]', 'display: none', 'position: absolute'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der kompakte Zuteilungsdialog fehlt: {$contract}");
    }
}
foreach (['.flz-planer-chip--editable:hover > .flz-planer-preference-panel', '.flz-planer-chip--editable:focus-within > .flz-planer-preference-panel', '.flz-planer-preference-marker--favorite', '.flz-planer-preference-option--favorite > span', 'color: #f5b301', '.flz-planer-shift-notes'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der zugängliche Schichtpräferenz-Popoververtrag fehlt: {$contract}");
    }
}
foreach (['.flz-planer-shift-note-editor', '.flz-planer-shift-note-actions', '.flz-planer-shift-note-editor[hidden]', 'display: none'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der horizontale Anmerkungs- und Editorvertrag fehlt: {$contract}");
    }
}
foreach (['id="month-prev"', 'id="month-next"', 'aria-label="Vorheriger Monat"', 'aria-label="Nächster Monat"', 'min="2000-01"', 'max="2100-12"', 'required'] as $contract) {
    if (!str_contains($template, $contract)) {
        throw new RuntimeException("Die direkte Monatsnavigation fehlt: {$contract}");
    }
}
foreach (['id="flz-planer-tab-workload"', 'data-view="workload"', '>Auslastung</button>', 'aria-controls="flz-planer-workload-overlay"', 'aria-haspopup="dialog"', 'id="flz-planer-workload-overlay"', 'role="dialog"'] as $contract) {
    if (!str_contains($template, $contract)) {
        throw new RuntimeException("Der Auslastungstab fehlt: {$contract}");
    }
}
foreach (['.flz-planer-tab-area', '.flz-planer-workload-overlay', 'top: calc(100% + 6px)', 'z-index: 120'] as $contract) {
    if (!str_contains($css,$contract)) throw new RuntimeException("Der verankerte Auslastungs-Overlayvertrag fehlt: {$contract}");
}
foreach (['.flz-planer-month-table .flz-planer-week-column', '.flz-planer-month-table .flz-planer-week-cell', 'width: 1%', 'min-width: 2.7rem', 'vertical-align: middle', '.flz-planer-week-cell.flz-planer-capacity--within'] as $contract) {
    if (!str_contains($css,$contract)) throw new RuntimeException("Der kompakte, wochenübergreifende KW-Zellvertrag fehlt: {$contract}");
}
foreach (['.flz-planer-month-table tbody tr.flz-planer-week-start > *', 'border-top: 3px solid'] as $contract) {
    if (!str_contains($css,$contract)) throw new RuntimeException("Die kräftige Trennlinie zwischen Kalenderwochen fehlt: {$contract}");
}
foreach (['.flz-planer-month-table .flz-planer-vacation-column', '.flz-planer-month-table .flz-planer-vacation-cell', '.flz-planer-vacation-label', 'position: sticky', 'writing-mode: vertical-rl', 'transform: rotate(180deg)'] as $contract) {
    if (!str_contains($css,$contract)) throw new RuntimeException("Die vertikale, im sichtbaren Bereich gehaltene Urlaubsspalte fehlt: {$contract}");
}
foreach (['width: min(640px, calc(100vw - 24px))', 'padding: 8px', '.flz-planer-workload-view', '.flz-planer-week-capacities', '.flz-planer-proposal-candidates'] as $contract) {
    if (!str_contains($css, $contract)) throw new RuntimeException("Der kompakte Auslastungs-Overlayvertrag fehlt: {$contract}");
}
foreach (['.flz-planer-capacity--under', '.flz-planer-capacity--within', '.flz-planer-capacity--over', '#dcfce7', '#fee2e2', '.flz-planer-plan-proposal'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Die kompakte Auslastungsampel fehlt: {$contract}");
    }
}
foreach (['.flz-planer-desktop-plan', '.flz-planer-mobile-plan', 'display: none', '@media (max-width: 700px)', '.flz-planer-mobile-day', '.flz-planer-mobile-shift', 'min-height: 44px', 'overflow-x: hidden'] as $contract) {
    if (!str_contains($css, $contract)) {
        throw new RuntimeException("Der smartphone-taugliche Tageslistenvertrag fehlt: {$contract}");
    }
}

echo "FlzPlaner layout smoke test passed\n";

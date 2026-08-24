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
foreach (['id="month-prev"', 'id="month-next"', 'aria-label="Vorheriger Monat"', 'aria-label="Nächster Monat"', 'min="2000-01"', 'max="2100-12"', 'required'] as $contract) {
    if (!str_contains($template, $contract)) {
        throw new RuntimeException("Die direkte Monatsnavigation fehlt: {$contract}");
    }
}

echo "AdPlaner layout smoke test passed\n";

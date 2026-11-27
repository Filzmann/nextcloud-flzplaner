<?php
\OCP\Util::addScript('flzplaner', 'shared/api-client');
\OCP\Util::addScript('flzplaner', 'shared/model');
\OCP\Util::addScript('flzplaner', 'shared/repository');
\OCP\Util::addScript('flzplaner', 'shared/ui');
\OCP\Util::addScript('flzplaner', 'shared/overlay');
\OCP\Util::addScript('flzplaner', 'admin-access');
\OCP\Util::addScript('flzplaner', 'modules/api');
\OCP\Util::addScript('flzplaner', 'modules/ui');
\OCP\Util::addScript('flzplaner', 'models/assistant');
\OCP\Util::addScript('flzplaner', 'models/shift-candidate');
\OCP\Util::addScript('flzplaner', 'models/shift-definition');
\OCP\Util::addScript('flzplaner', 'models/shift-slot');
\OCP\Util::addScript('flzplaner', 'models/team-settings');
\OCP\Util::addScript('flzplaner', 'models/team');
\OCP\Util::addScript('flzplaner', 'models/day-note');
\OCP\Util::addScript('flzplaner', 'repositories/plan-repository');
\OCP\Util::addScript('flzplaner', 'components/candidate-chip');
\OCP\Util::addScript('flzplaner', 'components/day-note-control');
\OCP\Util::addScript('flzplaner', 'components/assignment-control');
\OCP\Util::addScript('flzplaner', 'components/shift-settings-list');
\OCP\Util::addScript('flzplaner', 'components/month-plan');
\OCP\Util::addScript('flzplaner', 'components/workload-panel');
\OCP\Util::addScript('flzplaner', 'components/settings-panel');
\OCP\Util::addScript('flzplaner', 'components/plan-chrome');
\OCP\Util::addScript('flzplaner', 'components/plan-panel');
\OCP\Util::addScript('flzplaner', 'modules/plan-app');
\OCP\Util::addScript('flzplaner', 'main');
\OCP\Util::addStyle('flzplaner', 'style');
?>

<div id="flzplaner-app">
    <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flzplaner"></div>
    <header class="flz-planer-head">
        <div class="flz-planer-title-row">
            <h1>Assistenzplanung</h1>
            <?php if ($_['showMissingAdminGrant'] ?? false): ?>
                <button type="button" id="flz-planer-admin-access-warning" class="flz-shared-warn-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="flz-planer-admin-access-overlay" aria-label="Fehlender fachlicher Admin-Vollzugriff: Freigabesteuerung im Overlay öffnen"><span aria-hidden="true">⚠</span></button>
            <?php endif; ?>
        </div>
        <div class="flz-planer-controls">
            <label>
                Assistenznehmer
                <select id="team-select"></select>
            </label>
            <label>
                Monat
                <span class="flz-planer-month-control">
                    <button type="button" id="month-prev" class="flz-planer-icon-button" aria-label="Vorheriger Monat">‹</button>
                    <input id="month-input" type="month" min="2000-01" max="2100-12" required>
                    <button type="button" id="month-next" class="flz-planer-icon-button" aria-label="Nächster Monat">›</button>
                </span>
            </label>
        </div>
    </header>

    <div class="flz-planer-tab-area">
        <nav class="flz-planer-tabs" role="tablist" aria-label="Planbereiche">
            <button type="button" id="flz-planer-tab-month" class="flz-planer-tab is-active" role="tab" aria-controls="flz-planer-panel" aria-selected="true" tabindex="0" data-view="month">Wunschplan</button>
            <button type="button" id="flz-planer-tab-workload" class="flz-planer-tab" role="tab" aria-controls="flz-planer-workload-overlay" aria-haspopup="dialog" aria-expanded="false" aria-selected="false" tabindex="-1" data-view="workload">Auslastung</button>
            <button type="button" id="flz-planer-tab-settings" class="flz-planer-tab" role="tab" aria-controls="flz-planer-panel" aria-selected="false" tabindex="-1" data-view="settings">Einstellungen</button>
        </nav>
        <section id="flz-planer-workload-overlay" class="flz-planer-workload-overlay" role="dialog" aria-modal="false" aria-labelledby="flz-planer-tab-workload" hidden></section>
    </div>

    <div id="flz-planer-notice" class="flz-planer-notice" hidden></div>
    <main id="flz-planer-panel" class="flz-planer-panel" role="tabpanel" aria-labelledby="flz-planer-tab-month" tabindex="0"></main>

    <?php if (($_['showMissingAdminGrant'] ?? false) || ($_['canManageAdminAccess'] ?? false)): ?>
    <div id="flz-planer-admin-access-overlay" class="flz-shared-overlay" hidden>
        <div class="flz-shared-overlay__panel" role="dialog" aria-modal="true" aria-labelledby="flz-planer-admin-access-overlay-title">
            <div class="flz-shared-overlay__head">
                <h2 id="flz-planer-admin-access-overlay-title">Zeitlich begrenzter Admin-Vollzugriff</h2>
                <button type="button" id="flz-planer-admin-access-overlay-close" class="flz-planer-icon-button" aria-label="Schließen" title="Schließen">×</button>
            </div>
            <div class="flz-shared-overlay__content">
                <?php if ($_['showMissingAdminGrant'] ?? false): ?>
                    <div class="flz-shared-overlay__warning">
                        <strong>Kein fachlicher Admin-Vollzugriff aktiv.</strong>
                        <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Datenschutzbeauftragte und zuständige Einsatzbegleitungen können eine app-lokale Freigabe von höchstens 24 Stunden erteilen.</p>
                    </div>
                <?php endif; ?>
                <?php if ($_['canManageAdminAccess'] ?? false): ?>
                    <section id="flz-planer-full-access" class="flz-planer-admin-access" aria-labelledby="flz-planer-full-access-heading">
                        <h2 id="flz-planer-full-access-heading">Freigabesteuerung</h2>
                        <p>Datenschutzbeauftragte und zuständige Einsatzbegleitungen dürfen einem aktuellen Nextcloud-Administrationskonto fachlichen Vollzugriff erteilen oder ihn widerrufen. Zuständig ist eine Einsatzbegleitung nur mit aktueller Mitgliedschaft in mindestens einem Assistenzteam. Maximal 24 Stunden sind zulässig.</p>
                        <form id="flz-planer-full-access-form">
                            <label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label>
                            <label>Dauer
                                <select name="durationMinutes" required>
                                    <option value="60">1 Stunde</option>
                                    <option value="240">4 Stunden</option>
                                    <option value="480">8 Stunden</option>
                                    <option value="1440">24 Stunden</option>
                                </select>
                            </label>
                            <label><input id="flz-planer-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label>
                            <button type="submit" class="primary">Freigabe aktivieren</button>
                        </form>
                        <p id="flz-planer-full-access-status" role="status" aria-live="polite"></p>
                        <div class="flz-planer-table-wrap">
                            <table class="flz-planer-table">
                                <caption>Protokollierte Admin-Vollzugriffszeiträume</caption>
                                <thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead>
                                <tbody id="flz-planer-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

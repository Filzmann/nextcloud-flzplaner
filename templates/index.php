<?php
\OCP\Util::addScript('localbase', 'api/api-client');
\OCP\Util::addScript('flzplaner', 'admin-access');
\OCP\Util::addScript('flzplaner', 'modules/api');
\OCP\Util::addScript('localbase', 'ui/ui');
\OCP\Util::addScript('flzplaner', 'modules/ui');
\OCP\Util::addScript('localbase', 'models/model');
\OCP\Util::addScript('flzplaner', 'models/assistant');
\OCP\Util::addScript('flzplaner', 'models/shift-candidate');
\OCP\Util::addScript('flzplaner', 'models/shift-definition');
\OCP\Util::addScript('flzplaner', 'models/shift-slot');
\OCP\Util::addScript('flzplaner', 'models/team-settings');
\OCP\Util::addScript('flzplaner', 'models/team');
\OCP\Util::addScript('flzplaner', 'models/day-note');
\OCP\Util::addScript('localbase', 'repositories/repository');
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
                <details class="flz-planer-admin-access-warning">
                    <summary aria-label="Informationen zum fehlenden fachlichen Admin-Vollzugriff"><span aria-hidden="true">⚠</span></summary>
                    <div class="flz-planer-admin-access-warning__panel">
                        <strong>Kein fachlicher Admin-Vollzugriff aktiv.</strong>
                        <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Mitglieder der Gruppe Datenschutzbeauftragte können eine app-lokale Freigabe von höchstens 24 Stunden erteilen.</p>
                        <?php if ($_['showAdminAccessLink'] ?? false): ?><a href="#flz-planer-full-access" target="_blank" rel="noopener noreferrer">Freigabesteuerung in neuem Tab öffnen</a><?php endif; ?>
                    </div>
                </details>
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

    <?php if ($_['canManageAdminAccess'] ?? false): ?>
        <section id="flz-planer-full-access" class="flz-planer-admin-access" aria-labelledby="flz-planer-full-access-heading">
            <h2 id="flz-planer-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h2>
            <p>Ausschließlich Mitglieder von Datenschutzbeauftragte dürfen einem aktuellen Nextcloud-Administrationskonto fachlichen Vollzugriff erteilen oder ihn widerrufen. Maximal 24 Stunden sind zulässig.</p>
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
</div>

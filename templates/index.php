<?php
\OCP\Util::addScript('localbase', 'api/api-client');
\OCP\Util::addScript('adplaner', 'admin-access');
\OCP\Util::addScript('adplaner', 'modules/api');
\OCP\Util::addScript('localbase', 'ui/ui');
\OCP\Util::addScript('adplaner', 'modules/ui');
\OCP\Util::addScript('localbase', 'models/model');
\OCP\Util::addScript('adplaner', 'models/assistant');
\OCP\Util::addScript('adplaner', 'models/shift-candidate');
\OCP\Util::addScript('adplaner', 'models/shift-definition');
\OCP\Util::addScript('adplaner', 'models/shift-slot');
\OCP\Util::addScript('adplaner', 'models/team-settings');
\OCP\Util::addScript('adplaner', 'models/team');
\OCP\Util::addScript('adplaner', 'models/day-note');
\OCP\Util::addScript('localbase', 'repositories/repository');
\OCP\Util::addScript('adplaner', 'repositories/plan-repository');
\OCP\Util::addScript('adplaner', 'components/candidate-chip');
\OCP\Util::addScript('adplaner', 'components/day-note-control');
\OCP\Util::addScript('adplaner', 'components/assignment-control');
\OCP\Util::addScript('adplaner', 'components/shift-settings-list');
\OCP\Util::addScript('adplaner', 'components/month-plan');
\OCP\Util::addScript('adplaner', 'components/workload-panel');
\OCP\Util::addScript('adplaner', 'components/settings-panel');
\OCP\Util::addScript('adplaner', 'components/plan-chrome');
\OCP\Util::addScript('adplaner', 'components/plan-panel');
\OCP\Util::addScript('adplaner', 'modules/plan-app');
\OCP\Util::addScript('adplaner', 'main');
\OCP\Util::addStyle('adplaner', 'style');
?>

<div id="adplaner-app">
    <div class="orgsuite-host" data-orgsuite data-suite="ad" data-current-app="adplaner"></div>
    <header class="adp-head">
        <div class="adp-title-row">
            <h1>Assistenzplanung</h1>
            <?php if ($_['showMissingAdminGrant'] ?? false): ?>
                <details class="adp-admin-access-warning">
                    <summary aria-label="Informationen zum fehlenden fachlichen Admin-Vollzugriff"><span aria-hidden="true">⚠</span></summary>
                    <div class="adp-admin-access-warning__panel">
                        <strong>Kein fachlicher Admin-Vollzugriff aktiv.</strong>
                        <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Mitglieder der Gruppe Datenschutzbeauftragte können eine app-lokale Freigabe von höchstens 24 Stunden erteilen.</p>
                        <?php if ($_['showAdminAccessLink'] ?? false): ?><a href="#adp-full-access" target="_blank" rel="noopener noreferrer">Freigabesteuerung in neuem Tab öffnen</a><?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        <div class="adp-controls">
            <label>
                Assistenznehmer
                <select id="team-select"></select>
            </label>
            <label>
                Monat
                <span class="adp-month-control">
                    <button type="button" id="month-prev" class="adp-icon-button" aria-label="Vorheriger Monat">‹</button>
                    <input id="month-input" type="month" min="2000-01" max="2100-12" required>
                    <button type="button" id="month-next" class="adp-icon-button" aria-label="Nächster Monat">›</button>
                </span>
            </label>
        </div>
    </header>

    <?php if ($_['canManageAdminAccess'] ?? false): ?>
        <section id="adp-full-access" class="adp-admin-access" aria-labelledby="adp-full-access-heading">
            <h2 id="adp-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h2>
            <p>Ausschließlich Mitglieder von Datenschutzbeauftragte dürfen einem aktuellen Nextcloud-Administrationskonto fachlichen Vollzugriff erteilen oder ihn widerrufen. Maximal 24 Stunden sind zulässig.</p>
            <form id="adp-full-access-form">
                <label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label>
                <label>Dauer
                    <select name="durationMinutes" required>
                        <option value="60">1 Stunde</option>
                        <option value="240">4 Stunden</option>
                        <option value="480">8 Stunden</option>
                        <option value="1440">24 Stunden</option>
                    </select>
                </label>
                <label><input id="adp-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label>
                <button type="submit" class="primary">Freigabe aktivieren</button>
            </form>
            <p id="adp-full-access-status" role="status" aria-live="polite"></p>
            <div class="adp-table-wrap">
                <table class="adp-table">
                    <caption>Protokollierte Admin-Vollzugriffszeiträume</caption>
                    <thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead>
                    <tbody id="adp-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <div class="adp-tab-area">
        <nav class="adp-tabs" role="tablist" aria-label="Planbereiche">
            <button type="button" id="adp-tab-month" class="adp-tab is-active" role="tab" aria-controls="adp-panel" aria-selected="true" tabindex="0" data-view="month">Wunschplan</button>
            <button type="button" id="adp-tab-workload" class="adp-tab" role="tab" aria-controls="adp-workload-overlay" aria-haspopup="dialog" aria-expanded="false" aria-selected="false" tabindex="-1" data-view="workload">Auslastung</button>
            <button type="button" id="adp-tab-settings" class="adp-tab" role="tab" aria-controls="adp-panel" aria-selected="false" tabindex="-1" data-view="settings">Einstellungen</button>
        </nav>
        <section id="adp-workload-overlay" class="adp-workload-overlay" role="dialog" aria-modal="false" aria-labelledby="adp-tab-workload" hidden></section>
    </div>

    <div id="adp-notice" class="adp-notice" hidden></div>
    <main id="adp-panel" class="adp-panel" role="tabpanel" aria-labelledby="adp-tab-month" tabindex="0"></main>
</div>

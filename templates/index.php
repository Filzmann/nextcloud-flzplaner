<?php
\OCP\Util::addScript('localbase', 'api/api-client');
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
        <h1>Assistenzplanung</h1>
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

    <nav class="adp-tabs" role="tablist" aria-label="Planbereiche">
        <button type="button" id="adp-tab-month" class="adp-tab is-active" role="tab" aria-controls="adp-panel" aria-selected="true" tabindex="0" data-view="month">Wunschplan</button>
        <button type="button" id="adp-tab-settings" class="adp-tab" role="tab" aria-controls="adp-panel" aria-selected="false" tabindex="-1" data-view="settings">Einstellungen</button>
    </nav>

    <div id="adp-notice" class="adp-notice" hidden></div>
    <main id="adp-panel" class="adp-panel" role="tabpanel" aria-labelledby="adp-tab-month" tabindex="0"></main>
</div>

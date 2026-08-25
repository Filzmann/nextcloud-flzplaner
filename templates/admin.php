<?php
\OCP\Util::addScript('localbase', 'api/api-client');
\OCP\Util::addScript('adplaner', 'admin');
\OCP\Util::addStyle('adplaner', 'admin');
?>
<section id="adplaner-admin" class="section adp-admin" aria-labelledby="adp-admin-heading">
    <h2 id="adp-admin-heading">Assistenzplanung</h2>
    <section class="adp-admin-panel" aria-labelledby="adp-full-access-heading">
        <h3 id="adp-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h3>
        <p>Native Nextcloud-Administration erteilt keinen automatischen Zugriff auf den fachlichen Demo-Datenpfad. Die Freigabe gilt je Administrationskonto für maximal 24 Stunden.</p>
        <form id="adp-full-access-form"><label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label><label>Dauer <select name="durationMinutes" required><option value="60">1 Stunde</option><option value="240">4 Stunden</option><option value="480">8 Stunden</option><option value="1440">24 Stunden</option></select></label><label><input id="adp-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label><button type="submit" class="primary">Freigabe aktivieren</button></form>
        <p id="adp-full-access-status" class="adp-admin-notice" role="status" aria-live="polite"></p>
        <div class="adp-table-wrap"><table><caption>Protokollierte Admin-Vollzugriffszeiträume</caption><thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead><tbody id="adp-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody></table></div>
    </section>
    <section class="adp-admin-panel" aria-labelledby="adp-demo-heading">
        <h3 id="adp-demo-heading">Demo-Pack</h3>
        <p>Das Pack legt Team A, Team B und Team C mit synthetischen Einsatzbegleitungen, Assistenzkräften und Standardschichten an. Es wird ausschließlich nach dieser Bestätigung installiert.</p>
        <p>Fremde Konten und read-only LDAP-Gruppen werden vor der ersten Änderung abgewiesen.</p>
        <p id="adp-demo-notice" class="adp-admin-notice" role="status" aria-live="polite" hidden></p>
        <label class="adp-demo-confirm"><input id="adp-demo-confirm" type="checkbox"> Ich bestätige die Installation synthetischer Demodaten.</label>
        <button id="adp-demo-install" type="button" class="primary" disabled>Planer-Demo-Pack installieren</button>
    </section>
</section>

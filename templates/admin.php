<?php
\OCP\Util::addScript('localbase', 'api/api-client');
\OCP\Util::addScript('adplaner', 'admin');
\OCP\Util::addStyle('adplaner', 'admin');
?>
<section id="adplaner-admin" class="section adp-admin" aria-labelledby="adp-admin-heading">
    <h2 id="adp-admin-heading">Assistenzplanung</h2>
    <section class="adp-admin-panel" aria-labelledby="adp-demo-heading">
        <h3 id="adp-demo-heading">Demo-Pack</h3>
        <p>Das Pack legt Team A, Team B und Team C mit synthetischen Einsatzbegleitungen, Assistenzkräften und Standardschichten an. Es wird ausschließlich nach dieser Bestätigung installiert.</p>
        <p>Fremde Konten und read-only LDAP-Gruppen werden vor der ersten Änderung abgewiesen.</p>
        <p id="adp-demo-notice" class="adp-admin-notice" role="status" aria-live="polite" hidden></p>
        <label class="adp-demo-confirm"><input id="adp-demo-confirm" type="checkbox"> Ich bestätige die Installation synthetischer Demodaten.</label>
        <button id="adp-demo-install" type="button" class="primary" disabled>Planer-Demo-Pack installieren</button>
    </section>
</section>

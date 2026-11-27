<?php
\OCP\Util::addScript('flzplaner', 'shared/api-client');
\OCP\Util::addScript('flzplaner', 'admin');
\OCP\Util::addStyle('flzplaner', 'admin');
?>
<section id="flzplaner-admin" class="section flz-planer-admin" aria-labelledby="flz-planer-admin-heading">
    <h2 id="flz-planer-admin-heading"><?php p($l->t('Assistenzplanung')); ?></h2>
    <section class="flz-planer-admin-panel" aria-labelledby="flz-planer-demo-heading">
        <h3 id="flz-planer-demo-heading"><?php p($l->t('Demo-Pack')); ?></h3>
        <p><?php p($l->t('Das Pack legt Team A, Team B und Team C mit synthetischen Einsatzbegleitungen, Assistenzkräften und Standardschichten an. Es wird ausschließlich nach dieser Bestätigung installiert.')); ?></p>
        <p><?php p($l->t('Fremde Konten und read-only LDAP-Gruppen werden vor der ersten Änderung abgewiesen.')); ?></p>
        <p id="flz-planer-demo-notice" class="flz-planer-admin-notice" role="status" aria-live="polite" hidden></p>
        <label class="flz-planer-demo-confirm"><input id="flz-planer-demo-confirm" type="checkbox"> <?php p($l->t('Ich bestätige die Installation synthetischer Demodaten.')); ?></label>
        <button id="flz-planer-demo-install" type="button" class="primary" disabled><?php p($l->t('Planer-Demo-Pack installieren')); ?></button>
    </section>
</section>

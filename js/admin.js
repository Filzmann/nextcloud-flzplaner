(function() {
    'use strict';
    const confirmation = document.getElementById('adp-demo-confirm');
    const button = document.getElementById('adp-demo-install');
    const notice = document.getElementById('adp-demo-notice');
    if (!confirmation || !button || !notice) return;
    const client = new window.LocalBase.api.ApiClient({ appId: 'adplaner' });
    confirmation.addEventListener('change', () => { button.disabled = !confirmation.checked; });
    button.addEventListener('click', async () => {
        if (!confirmation.checked || button.disabled) return;
        button.disabled = true;
        notice.hidden = false;
        notice.className = 'adp-admin-notice';
        notice.textContent = 'Demo-Pack wird geprüft und installiert …';
        try {
            const response = await client.request('/api/admin/demo-pack/install', { method: 'POST', body: '{"confirmed":true}' });
            notice.classList.add('is-success');
            notice.textContent = `${response.result.teams.join(', ')} wurden als Demoteams angelegt.`;
            confirmation.checked = false;
        } catch (error) {
            notice.classList.add('is-error');
            notice.textContent = error.message || 'Das Demo-Pack konnte nicht installiert werden.';
            button.disabled = false;
        }
    });
}());

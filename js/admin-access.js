(function() {
    'use strict';

    const form = document.getElementById('adp-full-access-form');
    const history = document.getElementById('adp-full-access-history');
    const status = document.getElementById('adp-full-access-status');
    if (!form || !history || !status) return;

    const client = new window.LocalBase.api.ApiClient({ appId: 'adplaner' });

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.setAttribute('role', isError ? 'alert' : 'status');
    };

    const formatDateTime = value => {
        if (!value) return '—';
        return new Intl.DateTimeFormat('de-DE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
    };

    const renderGrant = grant => {
        const row = document.createElement('tr');
        const active = !grant.revokedAt && Date.parse(grant.startsAt) <= Date.now() && Date.parse(grant.endsAt) > Date.now();
        const values = [
            grant.targetUid,
            grant.grantedBy,
            formatDateTime(grant.startsAt),
            formatDateTime(grant.endsAt),
            `${formatDateTime(grant.revokedAt || grant.endsAt)} · ${active ? 'aktiv' : (grant.revokedAt ? 'widerrufen' : 'abgelaufen')}`,
        ];
        values.forEach(value => {
            const cell = document.createElement('td');
            cell.textContent = String(value || '—');
            row.append(cell);
        });
        const action = document.createElement('td');
        if (active) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.revokeUid = grant.targetUid;
            button.textContent = 'Widerrufen';
            action.append(button);
        } else {
            action.textContent = '—';
        }
        row.append(action);
        return row;
    };

    const loadFullAccess = async () => {
        try {
            const state = await client.request('/api/admin/full-access');
            history.replaceChildren();
            if (!state.history?.length) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 6;
                cell.textContent = 'Noch keine Freigabe protokolliert.';
                row.append(cell);
                history.append(row);
                return;
            }
            state.history.forEach(grant => history.append(renderGrant(grant)));
        } catch (error) {
            setStatus(error.message || 'Die Vollzugriffshistorie konnte nicht geladen werden.', true);
        }
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const fields = new FormData(form);
        if (fields.get('enabled') !== 'on') return;
        try {
            await client.request('/api/admin/full-access', {
                method: 'POST',
                body: JSON.stringify({
                    targetUid: String(fields.get('targetUid') || '').trim(),
                    durationMinutes: Number(fields.get('durationMinutes')),
                }),
            });
            form.elements.enabled.checked = false;
            setStatus('Der zeitlich begrenzte Vollzugriff wurde aktiviert.');
            await loadFullAccess();
        } catch (error) {
            setStatus(error.message || 'Der Vollzugriff konnte nicht aktiviert werden.', true);
        }
    });

    history.addEventListener('click', async event => {
        const button = event.target.closest('button[data-revoke-uid]');
        if (!button) return;
        button.disabled = true;
        try {
            await client.request(`/api/admin/full-access/${encodeURIComponent(button.dataset.revokeUid)}`, { method: 'DELETE' });
            setStatus('Der Vollzugriff wurde widerrufen.');
            await loadFullAccess();
        } catch (error) {
            button.disabled = false;
            setStatus(error.message || 'Der Vollzugriff konnte nicht widerrufen werden.', true);
        }
    });

    void loadFullAccess();
}());

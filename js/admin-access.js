(function() {
    'use strict';

    const shared = window.OCA.FlzPlaner?.Shared?.ui;
    if (!shared?.AdminAccessOverlay || !window.OCA.FlzPlaner?.Shared?.api?.ApiClient) return;

    const client = new window.OCA.FlzPlaner.Shared.api.ApiClient({ appId: 'flzplaner' });

    new shared.AdminAccessOverlay({
        overlayId: 'flz-planer-admin-access-overlay',
        triggerId: 'flz-planer-admin-access-warning',
        closeId: 'flz-planer-admin-access-overlay-close',
        formId: 'flz-planer-full-access-form',
        historyId: 'flz-planer-full-access-history',
        statusId: 'flz-planer-full-access-status',
        api: {
            getState: () => client.request('/api/admin/full-access'),
            activate: (targetUid, durationMinutes) => client.request('/api/admin/full-access', {
                method: 'POST',
                body: JSON.stringify({ targetUid, durationMinutes }),
            }),
            revoke: (targetUid) => client.request(`/api/admin/full-access/${encodeURIComponent(targetUid)}`, { method: 'DELETE' }),
        },
    });
}());

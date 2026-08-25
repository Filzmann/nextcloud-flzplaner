(function() {
    'use strict';
    const confirmation = document.getElementById('adp-demo-confirm');
    const button = document.getElementById('adp-demo-install');
    const notice = document.getElementById('adp-demo-notice');
    if (!confirmation || !button || !notice) return;
    const client = new window.LocalBase.api.ApiClient({ appId: 'adplaner' });
    const fullAccessForm = document.getElementById('adp-full-access-form');
    const fullAccessHistory = document.getElementById('adp-full-access-history');
    const fullAccessStatus = document.getElementById('adp-full-access-status');
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

    if (fullAccessForm && fullAccessHistory && fullAccessStatus) {
    const dateLabel=value=>value?new Date(value).toLocaleString():'—';
    async function loadFullAccess(){try{const state=await client.request('/api/admin/full-access');fullAccessHistory.replaceChildren();if(!state.history?.length){const row=document.createElement('tr');const cell=document.createElement('td');cell.colSpan=6;cell.textContent='Noch keine Freigaben protokolliert.';row.append(cell);fullAccessHistory.append(row);return;}state.history.forEach(grant=>{const row=document.createElement('tr');const active=!grant.revokedAt&&Date.parse(grant.startsAt)<=Date.now()&&Date.parse(grant.endsAt)>Date.now();[grant.targetUid,grant.grantedBy,dateLabel(grant.startsAt),dateLabel(grant.endsAt),grant.revokedAt?dateLabel(grant.revokedAt):(active?'Aktiv':'Planmäßig beendet')].forEach(value=>{const cell=document.createElement('td');cell.textContent=value;row.append(cell);});const action=document.createElement('td');if(active){const revoke=document.createElement('button');revoke.type='button';revoke.textContent='Widerrufen';revoke.dataset.revokeUid=grant.targetUid;action.append(revoke);}row.append(action);fullAccessHistory.append(row);});}catch(error){fullAccessStatus.textContent=error.message||'Die Vollzugriffshistorie konnte nicht geladen werden.';}}
    fullAccessForm.addEventListener('submit',async event=>{event.preventDefault();const data=new FormData(fullAccessForm);if(data.get('enabled')!=='on')return;try{await client.request('/api/admin/full-access',{method:'POST',body:JSON.stringify({targetUid:String(data.get('targetUid')||'').trim(),durationMinutes:Number(data.get('durationMinutes'))})});fullAccessForm.elements.enabled.checked=false;fullAccessStatus.textContent='Der zeitlich begrenzte Vollzugriff wurde aktiviert.';await loadFullAccess();}catch(error){fullAccessStatus.textContent=error.message||'Der Vollzugriff konnte nicht aktiviert werden.';}});
    fullAccessHistory.addEventListener('click',async event=>{const revoke=event.target.closest('button[data-revoke-uid]');if(!revoke)return;revoke.disabled=true;try{await client.request('/api/admin/full-access/'+encodeURIComponent(revoke.dataset.revokeUid),{method:'DELETE'});fullAccessStatus.textContent='Der Vollzugriff wurde widerrufen.';await loadFullAccess();}catch(error){revoke.disabled=false;fullAccessStatus.textContent=error.message||'Der Vollzugriff konnte nicht widerrufen werden.';}});
    void loadFullAccess();
    }
}());

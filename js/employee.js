(function() {
  function formatDateFr(iso) {
    if (!iso) return '';
    const parts = iso.split('-');
    if (parts.length !== 3) return iso;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  async function loadMyLeaves() {
    const list = document.getElementById('myLeaves');
    const empty = document.getElementById('empEmptyHint');
    if (!list) return;
    list.innerHTML = '';
    try {
      console.log('[talk_rh] employee.js: fetching my leaves…');
      const res = await fetch(OC.generateUrl('/apps/talk_rh/api/leaves'));
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      console.log('[talk_rh] employee.js: fetched', Array.isArray(data.leaves) ? data.leaves.length : 'n/a', 'leaves');
      if (!data.leaves || data.leaves.length === 0) {
        if (empty) empty.style.display = '';
        return;
      }
      if (empty) empty.style.display = 'none';
      data.leaves.forEach(l => {
        const li = document.createElement('li');
        li.className = 'talkrh-card';

        const title = document.createElement('div');
        title.className = 'title';
        title.textContent = `#${l.id}`;

        const meta = document.createElement('div');
        meta.className = 'talkrh-meta';
        meta.textContent = `${formatDateFr(l.start_date)} → ${formatDateFr(l.end_date)}` + (l.reason ? ` • Raison: ${l.reason}` : '');

        const badges = document.createElement('div');
        badges.className = 'talkrh-badges';
        const type = document.createElement('span');
        type.className = 'talkrh-badge';
        type.textContent = l.type === 'paid' ? 'Payé' : (l.type === 'unpaid' ? 'Non payé' : 'Maladie');
        const status = document.createElement('span');
        status.className = 'talkrh-badge badge-' + l.status;
        status.textContent = l.status === 'pending' ? 'En attente' : (l.status === 'approved' ? 'Approuvée' : 'Refusée');
        badges.appendChild(type);
        badges.appendChild(status);
        if (l.admin_comment) {
          const comment = document.createElement('span');
          comment.className = 'talkrh-badge';
          comment.textContent = 'Commentaire: ' + l.admin_comment;
          badges.appendChild(comment);
        }

        const actions = document.createElement('div');
        actions.className = 'talkrh-actions';
        if (l.status === 'pending') {
          const del = document.createElement('button');
          del.className = 'danger';
          del.textContent = 'Supprimer';
          del.onclick = async () => {
            if (!confirm('Supprimer cette demande ?')) return;
            await fetch(OC.generateUrl('/apps/talk_rh/api/leaves/' + l.id), { method: 'DELETE' });
            loadMyLeaves();
          };
          actions.appendChild(del);
        }

        li.appendChild(title);
        li.appendChild(meta);
        li.appendChild(badges);
        li.appendChild(actions);
        list.appendChild(li);
      });
    } catch (e) {
      const li = document.createElement('li');
      li.textContent = 'Erreur lors du chargement de vos demandes: ' + e.message;
      list.appendChild(li);
      console.error('[talk_rh] employee.js: error fetching leaves', e);
    }
  }

  function bindCreate() {
    const btn = document.getElementById('createBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      const type = document.getElementById('type').value;
      const reason = document.getElementById('reason').value;
      if (!startDate || !endDate) {
        alert('Merci de renseigner les dates de début et de fin.');
        return;
      }
      const form = new FormData();
      form.append('startDate', startDate);
      form.append('endDate', endDate);
      form.append('type', type);
      form.append('reason', reason);
      try {
        btn.disabled = true;
        await fetch(OC.generateUrl('/apps/talk_rh/api/leaves'), { method: 'POST', body: form });
        await loadMyLeaves();
        document.getElementById('reason').value = '';
      } finally {
        btn.disabled = false;
      }
    });
  }

  async function loadIcsInfo() {
    const input = document.getElementById('icsUrl');
    if (!input) return;
    try {
      input.value = 'Chargement…';
      const res = await fetch(OC.generateUrl('/apps/talk_rh/api/ics/token'));
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      input.value = data.url || '';
    } catch (e) {
      input.value = 'Erreur de chargement de l’URL ICS';
      console.error('[talk_rh] employee.js: error fetching ics token/url', e);
    }
  }

  function bindIcsActions() {
    const copyBtn = document.getElementById('copyIcsUrl');
    const regenBtn = document.getElementById('regenIcsToken');
    const input = document.getElementById('icsUrl');
    if (copyBtn && input) {
      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(input.value);
          copyBtn.textContent = 'Copié!';
          setTimeout(() => (copyBtn.textContent = 'Copier'), 1200);
        } catch (e) {
          alert('Impossible de copier. Copiez manuellement.');
        }
      });
    }
    if (regenBtn) {
      regenBtn.addEventListener('click', async () => {
        if (!confirm('Regénérer le lien ICS ? Les anciens abonnements ne se mettront plus à jour.')) return;
        regenBtn.disabled = true;
        try {
          const res = await fetch(OC.generateUrl('/apps/talk_rh/api/ics/token'), { method: 'POST' });
          if (!res.ok) throw new Error('HTTP ' + res.status);
          await loadIcsInfo();
        } finally {
          regenBtn.disabled = false;
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[talk_rh] employee.js: DOMContentLoaded');
    bindCreate();
    loadMyLeaves();
    bindIcsActions();
    loadIcsInfo();
  });
})();

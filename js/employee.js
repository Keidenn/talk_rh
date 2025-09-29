(function() {
  // State for pagination
  let myLeavesData = [];
  let empPage = 1;
  let empPageSize = 10;

  function formatDateFr(iso) {
    if (!iso) return '';
    const parts = iso.split('-');
    if (parts.length !== 3) return iso;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  
  function formatDateLongFr(iso) {
    if (!iso) return '';
    try {
      const [y, m, d] = iso.split('-').map(x => parseInt(x, 10));
      const dt = new Date(y, (m - 1), d);
      return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(dt);
    } catch (e) { return formatDateFr(iso); }
  }

  // Presentational helper for capitalizing French long dates when needed
  function titleCaseFr(s) {
    if (!s) return s;
    return s.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
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
      myLeavesData = Array.isArray(data.leaves) ? data.leaves : [];
      renderMyLeaves();
    } catch (e) {
      const li = document.createElement('li');
      li.textContent = 'Erreur lors du chargement de vos demandes: ' + e.message;
      list.appendChild(li);
      console.error('[talk_rh] employee.js: error fetching leaves', e);
    }
  }

  function renderMyLeaves() {
    const list = document.getElementById('myLeaves');
    const empty = document.getElementById('empEmptyHint');
    const pageInfo = document.getElementById('empPageInfo');
    const prevBtn = document.getElementById('empPrev');
    const nextBtn = document.getElementById('empNext');
    if (!list) return;
    list.innerHTML = '';
    const total = myLeavesData.length;
    if (total === 0) {
      if (empty) empty.style.display = '';
      if (pageInfo) pageInfo.textContent = 'Page 0/0';
      if (prevBtn) prevBtn.disabled = true;
      if (nextBtn) nextBtn.disabled = true;
      return;
    }
    if (empty) empty.style.display = 'none';
    const pages = Math.max(1, Math.ceil(total / empPageSize));
    if (empPage > pages) empPage = pages;
    const startIdx = (empPage - 1) * empPageSize;
    const endIdx = Math.min(startIdx + empPageSize, total);
    const slice = myLeavesData.slice(startIdx, endIdx);
    if (pageInfo) pageInfo.textContent = `Page ${empPage}/${pages}`;
    if (prevBtn) prevBtn.disabled = empPage <= 1;
    if (nextBtn) nextBtn.disabled = empPage >= pages;
    slice.forEach(l => {
        const li = document.createElement('li');
        li.className = 'talkrh-card';

        const title = document.createElement('div');
        title.className = 'title';
        title.textContent = `#${l.id}`;

        const meta = document.createElement('div');
        meta.className = 'talkrh-meta';
        meta.textContent = `${formatDateLongFr(l.start_date)} → ${formatDateLongFr(l.end_date)}` + (l.reason ? ` • Raison: ${l.reason}` : '');

        const badges = document.createElement('div');
        badges.className = 'talkrh-badges';
        const type = document.createElement('span');
        type.className = 'talkrh-badge';
        type.textContent = l.type === 'paid' ? 'Soldé' : (l.type === 'unpaid' ? 'Sans Solde' : 'Anticipé');
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
            await loadMyLeaves();
          };
          actions.appendChild(del);
        }

        li.appendChild(title);
        li.appendChild(meta);
        li.appendChild(badges);
        li.appendChild(actions);
        list.appendChild(li);
      });
  }

  function bindCreate() {
    const btn = document.getElementById('createBtn');
    const onBehalfField = document.getElementById('onBehalfField');
    const onBehalfSel = document.getElementById('onBehalf');
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
      // Open modal to select day parts before sending
      openDayPartsModal(startDate, endDate, async (dayPartsMap) => {
        const form = new FormData();
        form.append('startDate', startDate);
        form.append('endDate', endDate);
        form.append('type', type);
        form.append('reason', reason);
        form.append('dayParts', JSON.stringify(dayPartsMap));
        // If manager selected an employee, include targetUid
        try {
          if (onBehalfField && onBehalfField.style.display !== 'none' && onBehalfSel) {
            const targetUid = onBehalfSel.value || '';
            if (targetUid) form.append('targetUid', targetUid);
          }
        } catch (_) {}
        try {
          btn.disabled = true;
          await fetch(OC.generateUrl('/apps/talk_rh/api/leaves'), { method: 'POST', body: form });
          await loadMyLeaves();
          document.getElementById('reason').value = '';
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  async function loadEmployeesIFManager() {
    const onBehalfField = document.getElementById('onBehalfField');
    const onBehalfSel = document.getElementById('onBehalf');
    if (!onBehalfField || !onBehalfSel) return;
    try {
      const res = await fetch(OC.generateUrl('/apps/talk_rh/api/my/employees'));
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const employees = Array.isArray(data.employees) ? data.employees : [];
      if (employees.length === 0) {
        onBehalfField.style.display = 'none';
        return;
      }
      onBehalfSel.innerHTML = '';
      const optSelf = document.createElement('option');
      optSelf.value = '';
      optSelf.textContent = 'Moi-même';
      onBehalfSel.appendChild(optSelf);
      employees.forEach(emp => {
        const opt = document.createElement('option');
        opt.value = emp.uid;
        opt.textContent = (emp.displayName || emp.uid) + ' (' + emp.uid + ')';
        onBehalfSel.appendChild(opt);
      });
      onBehalfField.style.display = '';
    } catch (e) {
      // If error, keep field hidden
      onBehalfField.style.display = 'none';
    }
  }

  function eachDate(startIso, endIso) {
    const results = [];
    const start = new Date(startIso);
    const end = new Date(endIso);
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      const y = d.getFullYear();
      const m = (d.getMonth() + 1).toString().padStart(2, '0');
      const day = d.getDate().toString().padStart(2, '0');
      results.push(`${y}-${m}-${day}`);
    }
    return results;
  }

  function closeModal() {
    const backdrop = document.getElementById('talkrhModalBackdrop');
    if (backdrop) backdrop.style.display = 'none';
  }

  function openDayPartsModal(startIso, endIso, onConfirm) {
    const backdrop = document.getElementById('talkrhModalBackdrop');
    const titleEl = document.getElementById('talkrhModalTitle');
    const bodyEl = document.getElementById('talkrhModalBody');
    if (!backdrop || !titleEl || !bodyEl) return;
    titleEl.textContent = 'Détail des journées';
    bodyEl.innerHTML = '';

    const info = document.createElement('div');
    info.className = 'talkrh-meta';
    info.textContent = 'Sélectionnez pour chaque jour: Journée complète, Matin, Après-midi. Utilisez "Tout sélectionner" pour appliquer à tous les jours.';
    bodyEl.appendChild(info);

    const actionsAll = document.createElement('div');
    actionsAll.className = 'talkrh-actions';
    const btnAllFull = document.createElement('button'); btnAllFull.textContent = 'Tout: Journée complète';
    const btnAllAM = document.createElement('button'); btnAllAM.textContent = 'Tout: Matinée';
    const btnAllPM = document.createElement('button'); btnAllPM.textContent = 'Tout: Après-midi';
    actionsAll.appendChild(btnAllFull);
    actionsAll.appendChild(btnAllAM);
    actionsAll.appendChild(btnAllPM);
    bodyEl.appendChild(actionsAll);

    const container = document.createElement('div');
    container.className = 'talkrh-day-details';
    const dates = eachDate(startIso, endIso);
    const selected = {};
    dates.forEach(dateIso => { selected[dateIso] = 'full'; });
    
    dates.forEach(dateIso => {
      const card = document.createElement('div');
      card.className = 'talkrh-card';
      const title = document.createElement('div');
      title.className = 'title';
      title.textContent = titleCaseFr(formatDateLongFr(dateIso));
      const opts = document.createElement('div');
      opts.className = 'option-cards';
      
      const mkCard = (label, value) => {
        const div = document.createElement('div');
        div.className = 'option-card' + (value === 'full' ? ' selected' : '');
        div.dataset.value = value;
        div.textContent = label;
        div.addEventListener('click', () => {
          selected[dateIso] = value;
          // toggle selection within this group
          opts.querySelectorAll('.option-card').forEach(el => el.classList.remove('selected'));
          div.classList.add('selected');
        });
        return div;
      };
      
      opts.appendChild(mkCard('Journée complète', 'full'));
      opts.appendChild(mkCard('Matinée', 'am'));
      opts.appendChild(mkCard('Après-midi', 'pm'));
      card.appendChild(title);
      card.appendChild(opts);
      container.appendChild(card);
    });
    bodyEl.appendChild(container);

    const actions = document.createElement('div');
    actions.className = 'talkrh-actions';
    const btnConfirm = document.createElement('button'); btnConfirm.className = 'button primary'; btnConfirm.textContent = 'Confirmer';
    const btnCancel = document.createElement('button'); btnCancel.className = 'button'; btnCancel.textContent = 'Annuler'; btnCancel.onclick = () => closeModal();
    actions.appendChild(btnConfirm);
    actions.appendChild(btnCancel);
    bodyEl.appendChild(actions);

    // Bulk actions
    function setAll(value) {
      dates.forEach(dateIso => {
        selected[dateIso] = value;
      });
      // Update UI
      container.querySelectorAll('.option-cards').forEach(group => {
        group.querySelectorAll('.option-card').forEach(el => {
          el.classList.toggle('selected', el.dataset.value === value);
        });
      });
    }
    btnAllFull.onclick = () => setAll('full');
    btnAllAM.onclick = () => setAll('am');
    btnAllPM.onclick = () => setAll('pm');

    btnConfirm.onclick = () => {
      const map = {};
      dates.forEach(dateIso => {
        map[dateIso] = selected[dateIso] || 'full';
      });
      closeModal();
      if (typeof onConfirm === 'function') onConfirm(map);
    };

    backdrop.style.display = 'block';
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
      input.value = "Erreur de chargement de l'URL ICS";
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

  // Fallback for radio "card" styles: add/remove 'selected' on labels when checked
  function initRadioCards() {
    try {
      const containers = document.querySelectorAll('.talkrh-actions');
      containers.forEach(container => {
        const radios = container.querySelectorAll('label > input[type="radio"]');
        radios.forEach(input => {
          const label = input.closest('label');
          if (!label) return;
          const updateGroup = () => {
            const name = input.name;
            const group = Array.from(container.querySelectorAll('input[type="radio"]').values()).filter(r => r.name === name);
            group.forEach(r => {
              const lbl = r.closest('label');
              if (lbl) lbl.classList.toggle('selected', r.checked);
            });
          };
          // Initialize and bind
          updateGroup();
          input.addEventListener('change', updateGroup);
        });
      });
    } catch (_) {}
  }

  document.addEventListener('DOMContentLoaded', () => {
    console.log('[talk_rh] employee.js: DOMContentLoaded');
    bindCreate();
    // Initial bulk load with global loader
    try { if (window.talkrhLoader) window.talkrhLoader.show(); } catch(_) {}
    const sizeSel = document.getElementById('empPageSize');
    const prevBtn = document.getElementById('empPrev');
    const nextBtn = document.getElementById('empNext');
    if (sizeSel) {
      sizeSel.addEventListener('change', () => {
        empPageSize = parseInt(sizeSel.value, 10) || 10;
        empPage = 1;
        renderMyLeaves();
      });
    }
    if (prevBtn) prevBtn.addEventListener('click', () => { if (empPage > 1) { empPage -= 1; renderMyLeaves(); } });
    if (nextBtn) nextBtn.addEventListener('click', () => { empPage += 1; renderMyLeaves(); });
    const closeBtn = document.getElementById('talkrhModalClose');
    const backdrop = document.getElementById('talkrhModalBackdrop');
    if (closeBtn) closeBtn.onclick = closeModal;
    if (backdrop) backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
    initRadioCards();
    bindIcsActions();
    // Load core data in parallel
    Promise.allSettled([
      loadEmployeesIFManager(),
      loadMyLeaves(),
      loadIcsInfo(),
    ]).finally(() => { try { if (window.talkrhLoader) window.talkrhLoader.hide(); } catch(_) {} });
  });
})();

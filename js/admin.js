(function() {
  // State
  let allLeaves = [];
  let currentFilterUser = 'ALL';
  let currentFilterStatus = 'ALL';
  const today = new Date();
  let currentYear = today.getFullYear();
  let currentMonth = today.getMonth(); // 0-11
  let currentView = 'calendar'; // 'calendar' | 'list'

  const monthNamesFr = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

  // Utils
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function isoFromDate(d) { return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); }
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

  function leavesFiltered() {
    let filtered = currentFilterUser === 'ALL' ? allLeaves : allLeaves.filter(l => l.uid === currentFilterUser);
    if (currentFilterStatus !== 'ALL') {
      filtered = filtered.filter(l => l.status === currentFilterStatus);
    }
    return filtered;
  }

  function renderList() {
    const listView = document.getElementById('adminListView');
    const tbody = document.querySelector('#adminListTable tbody');
    if (!listView || !tbody) return;
    tbody.innerHTML = '';
    const rows = leavesFiltered().slice().sort((a,b) => {
      // sort by start_date desc then id desc
      if (a.start_date === b.start_date) return (b.id || 0) - (a.id || 0);
      return a.start_date > b.start_date ? -1 : 1;
    });
    rows.forEach(l => {
      const tr = document.createElement('tr');
      const tdId = document.createElement('td');
      tdId.textContent = '#' + l.id;
      const tdUser = document.createElement('td');
      tdUser.textContent = l.uid;
      const tdStart = document.createElement('td');
      tdStart.textContent = formatDateLongFr(l.start_date);
      const tdEnd = document.createElement('td');
      tdEnd.textContent = formatDateLongFr(l.end_date);
      const tdType = document.createElement('td');
      tdType.textContent = l.type === 'paid' ? 'Soldé' : (l.type === 'unpaid' ? 'Sans Solde' : 'Anticipé');
      const tdStatus = document.createElement('td');
      tdStatus.textContent = l.status === 'pending' ? 'En attente' : (l.status === 'approved' ? 'Approuvée' : 'Refusée');
      const tdActions = document.createElement('td');
      if (l.status === 'pending') {
        const approve = document.createElement('button');
        approve.className = 'button icon-button approve';
        approve.title = 'Approuver';
        approve.textContent = '✓';
        approve.onclick = async () => {
          const form = new FormData();
          form.append('status', 'approved');
          await fetch(OC.generateUrl('/apps/talk_rh/api/admin/leaves/' + l.id + '/status'), { method: 'POST', body: form });
          await loadAll();
        };
        const reject = document.createElement('button');
        reject.className = 'button icon-button danger reject';
        reject.title = 'Refuser';
        reject.textContent = '✕';
        reject.onclick = async () => {
          const form = new FormData();
          form.append('status', 'rejected');
          await fetch(OC.generateUrl('/apps/talk_rh/api/admin/leaves/' + l.id + '/status'), { method: 'POST', body: form });
          await loadAll();
        };
        tdActions.appendChild(approve);
        tdActions.appendChild(reject);
      } else {
        tdActions.textContent = '—';
      }
      tr.appendChild(tdId);
      tr.appendChild(tdUser);
      tr.appendChild(tdStart);
      tr.appendChild(tdEnd);
      tr.appendChild(tdType);
      tr.appendChild(tdStatus);
      tr.appendChild(tdActions);
      tbody.appendChild(tr);
    });
  }

  function render() {
    if (currentView === 'calendar') {
      const cal = document.getElementsByClassName('talkrh-calendar')[0];
      const list = document.getElementById('adminListView');
      if (cal) cal.style.display = '';
      if (list) list.style.display = 'none';
      renderCalendar();
    } else {
      const cal = document.getElementsByClassName('talkrh-calendar')[0];
      const list = document.getElementById('adminListView');
      if (cal) cal.style.display = 'none';
      if (list) list.style.display = '';
      renderList();
    }
  }

  function populateFilter(leaves) {
    const sel = document.getElementById('filterUser');
    const selStatus = document.getElementById('filterStatus');
    if (!sel) return;
    const seen = new Set();
    const prev = sel.value || 'ALL';
    sel.innerHTML = '';
    const optAll = document.createElement('option');
    optAll.value = 'ALL';
    optAll.textContent = 'Tous les employés';
    sel.appendChild(optAll);
    leaves.forEach(l => {
      if (!seen.has(l.uid)) {
        seen.add(l.uid);
        const o = document.createElement('option');
        o.value = l.uid;
        o.textContent = l.uid;
        sel.appendChild(o);
      }
    });
    if ([...seen, 'ALL'].includes(prev)) {
      sel.value = prev;
      currentFilterUser = prev;
    } else {
      sel.value = 'ALL';
      currentFilterUser = 'ALL';
    }
    sel.onchange = () => {
      currentFilterUser = sel.value;
      render();
    };
    if (selStatus) {
      selStatus.onchange = () => {
        currentFilterStatus = selStatus.value;
        render();
      };
    }
  }

  function leavesForDate(iso) {
    let filtered = currentFilterUser === 'ALL' ? allLeaves : allLeaves.filter(l => l.uid === currentFilterUser);
    if (currentFilterStatus !== 'ALL') {
      filtered = filtered.filter(l => l.status === currentFilterStatus);
    }
    return filtered.filter(l => iso >= l.start_date && iso <= l.end_date);
  }

  function closeModal() {
    const backdrop = document.getElementById('talkrhModalBackdrop');
    if (backdrop) backdrop.style.display = 'none';
  }

  function openModalForDate(iso) {
    const backdrop = document.getElementById('talkrhModalBackdrop');
    const titleEl = document.getElementById('talkrhModalTitle');
    const bodyEl = document.getElementById('talkrhModalBody');
    if (!backdrop || !titleEl || !bodyEl) return;
    const items = leavesForDate(iso);
    if (!items.length) {
      closeModal();
      return;
    }
    titleEl.textContent = 'Détails du ' + formatDateLongFr(iso);
    bodyEl.innerHTML = '';
    items.forEach(l => {
      const card = document.createElement('div');
      card.className = 'talkrh-card';
      const head = document.createElement('div');
      head.className = 'title';
      head.textContent = `#${l.id} • ${l.uid}`;
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
        const approve = document.createElement('button');
        approve.textContent = 'Approuver';
        approve.onclick = async () => {
          const comment = prompt('Commentaire (optionnel)') || '';
          const form = new FormData();
          form.append('status', 'approved');
          if (comment) form.append('adminComment', comment);
          await fetch(OC.generateUrl('/apps/talk_rh/api/admin/leaves/' + l.id + '/status'), { method: 'POST', body: form });
          await loadAll();
          closeModal();
        };
        const reject = document.createElement('button');
        reject.className = 'danger';
        reject.textContent = 'Refuser';
        reject.onclick = async () => {
          const comment = prompt('Commentaire (optionnel)') || '';
          const form = new FormData();
          form.append('status', 'rejected');
          if (comment) form.append('adminComment', comment);
          await fetch(OC.generateUrl('/apps/talk_rh/api/admin/leaves/' + l.id + '/status'), { method: 'POST', body: form });
          await loadAll();
          closeModal();
        };
        actions.appendChild(approve);
        actions.appendChild(reject);
      }
      card.appendChild(head);
      card.appendChild(meta);
      card.appendChild(badges);
      card.appendChild(actions);
      bodyEl.appendChild(card);
    });
    backdrop.style.display = 'block';
  }

  async function openSettingsModal() {
    const backdrop = document.getElementById('talkrhModalBackdrop');
    const titleEl = document.getElementById('talkrhModalTitle');
    const bodyEl = document.getElementById('talkrhModalBody');
    if (!backdrop || !titleEl || !bodyEl) return;

    titleEl.textContent = 'Paramètres · Groupe administrateur';
    bodyEl.innerHTML = '';

    const field = document.createElement('div');
    field.className = 'field';
    const label = document.createElement('label');
    label.textContent = 'Groupe admin';
    label.htmlFor = 'settingsGroupSelect';
    const select = document.createElement('select');
    select.id = 'settingsGroupSelect';
    select.className = '';
    field.appendChild(label);
    field.appendChild(select);

    const membersTitle = document.createElement('h4');
    membersTitle.textContent = 'Membres du groupe';
    const membersList = document.createElement('ul');
    membersList.id = 'settingsMembersList';
    membersList.className = 'talkrh-list';

    const actions = document.createElement('div');
    actions.className = 'talkrh-actions';
    const saveBtn = document.createElement('button');
    saveBtn.className = 'button primary';
    saveBtn.textContent = 'Enregistrer';
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'button';
    cancelBtn.textContent = 'Annuler';
    cancelBtn.onclick = () => closeModal();
    actions.appendChild(saveBtn);
    actions.appendChild(cancelBtn);

    bodyEl.appendChild(field);
    bodyEl.appendChild(membersTitle);
    bodyEl.appendChild(membersList);
    bodyEl.appendChild(actions);

    async function loadMembers(groupId) {
      membersList.innerHTML = '';
      try {
        const res = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group/members') + '?groupId=' + encodeURIComponent(groupId));
        const data = await res.json();
        const mem = Array.isArray(data.members) ? data.members : [];
        if (mem.length === 0) {
          const li = document.createElement('li');
          li.textContent = 'Aucun membre dans ce groupe.';
          membersList.appendChild(li);
        } else {
          mem.forEach(u => {
            const li = document.createElement('li');
            li.textContent = `${u.displayName || u.uid} (${u.uid})`;
            membersList.appendChild(li);
          });
        }
      } catch (e) {
        const li = document.createElement('li');
        li.textContent = 'Erreur de chargement des membres.';
        membersList.appendChild(li);
      }
    }

    try {
      // Load current group id
      const currentRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group'));
      const currentData = await currentRes.json();
      const currentGid = currentData.groupId || '';
      // Load groups
      const groupsRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/groups'));
      const groupsData = await groupsRes.json();
      const groups = Array.isArray(groupsData.groups) ? groupsData.groups : [];
      select.innerHTML = '';
      groups.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g.id;
        opt.textContent = g.displayName || g.id;
        select.appendChild(opt);
      });
      if (currentGid && groups.some(g => g.id === currentGid)) {
        select.value = currentGid;
      }
      select.onchange = () => loadMembers(select.value);
      await loadMembers(select.value);

      saveBtn.onclick = async () => {
        const form = new FormData();
        form.append('groupId', select.value);
        await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group'), { method: 'POST', body: form });
        closeModal();
      };
    } catch (e) {
      const li = document.createElement('div');
      li.textContent = 'Erreur de chargement de la configuration.';
      bodyEl.appendChild(li);
    }

    backdrop.style.display = 'block';
  }

  function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const label = document.getElementById('monthLabel');
    if (!grid || !label) return;
    grid.innerHTML = '';
    label.textContent = monthNamesFr[currentMonth] + ' ' + currentYear;

    // Compute first day (Mon-based) and total cells (6 weeks)
    const firstOfMonth = new Date(currentYear, currentMonth, 1);
    const startWeekdayMonBased = (firstOfMonth.getDay() + 6) % 7; // 0 = Monday
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const prevMonthDays = startWeekdayMonBased; // number of days from previous month to show
    const totalCells = 42; // 6 weeks x 7
    const startDate = new Date(currentYear, currentMonth, 1 - prevMonthDays);

    for (let i = 0; i < totalCells; i++) {
      const d = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() + i);
      const iso = isoFromDate(d);
      const inCurrent = d.getMonth() === currentMonth;
      const isToday = iso === isoFromDate(today);

      const cell = document.createElement('div');
      cell.className = 'day-cell' + (inCurrent ? '' : ' outside') + (isToday ? ' day-today' : '');
      const header = document.createElement('div');
      header.className = 'day-header';
      const num = document.createElement('div');
      num.className = 'day-number';
      num.textContent = d.getDate();
      header.appendChild(num);
      cell.appendChild(header);

      const events = document.createElement('div');
      events.className = 'events';
      const items = leavesForDate(iso);
      items.forEach(l => {
        const ev = document.createElement('div');
        ev.className = 'event-badge talkrh-badge badge-' + l.status;
        const typeLabel = l.type === 'paid' ? 'Soldé' : (l.type === 'unpaid' ? 'Sans Solde' : 'Anticipé');
        const statusLabel = l.status === 'pending' ? 'En attente' : (l.status === 'approved' ? 'Approuvée' : 'Refusée');
        const who = currentFilterUser === 'ALL' ? (l.uid + ' • ') : '';
        ev.textContent = who + typeLabel + ' · ' + statusLabel;
        events.appendChild(ev);
      });
      cell.appendChild(events);

      cell.addEventListener('click', () => openModalForDate(iso));
      grid.appendChild(cell);
    }
  }

  async function loadAll() {
    try {
      const res = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/leaves'));
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      allLeaves = Array.isArray(data.leaves) ? data.leaves : [];
      populateFilter(allLeaves);
      render();
    } catch (e) {
      console.error('[talk_rh] admin.js: error fetching leaves', e);
    }
  }

  function bindMonthNav() {
    const prev = document.getElementById('prevMonth');
    const next = document.getElementById('nextMonth');
    if (prev) prev.onclick = () => { currentMonth -= 1; if (currentMonth < 0) { currentMonth = 11; currentYear -= 1; } renderCalendar(); };
    if (next) next.onclick = () => { currentMonth += 1; if (currentMonth > 11) { currentMonth = 0; currentYear += 1; } renderCalendar(); };
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindMonthNav();
    // Modal close bindings
    const closeBtn = document.getElementById('talkrhModalClose');
    const backdrop = document.getElementById('talkrhModalBackdrop');
    if (closeBtn) closeBtn.onclick = closeModal;
    if (backdrop) backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
    const openSettingsBtn = document.getElementById('openSettings');
    if (openSettingsBtn) openSettingsBtn.onclick = openSettingsModal;
    const navViewCal = document.getElementById('navViewCalendar');
    const navViewList = document.getElementById('navViewList');
    if (navViewCal) navViewCal.addEventListener('click', (e) => { e.preventDefault(); currentView = 'calendar'; render(); });
    if (navViewList) navViewList.addEventListener('click', (e) => { e.preventDefault(); currentView = 'list'; render(); });
    loadAll();
  });
})();

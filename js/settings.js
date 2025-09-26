document.addEventListener('DOMContentLoaded', async () => {
  const groupSelect = document.getElementById('settingsGroupSelect');
  const membersList = document.getElementById('settingsMembersList');
  const saveBtn = document.getElementById('saveSettingsBtn');
  const talkToggle = document.getElementById('talkToggle');
  const saveTalkBtn = document.getElementById('saveTalkBtn');
  const talkChannelSelect = document.getElementById('talkChannelSelect');
  const saveTalkChannelBtn = document.getElementById('saveTalkChannelBtn');
  if (!groupSelect || !membersList || !saveBtn) return;

  async function loadMembers(groupId) {
    membersList.innerHTML = '<li>Chargement...</li>';
    try {
      const res = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group/members') + '?groupId=' + encodeURIComponent(groupId));
      const data = await res.json();
      const members = Array.isArray(data.members) ? data.members : [];
      membersList.innerHTML = '';
      if (members.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'Aucun membre dans ce groupe.';
        membersList.appendChild(li);
      } else {
        members.forEach(user => {
          const li = document.createElement('li');
          li.className = 'talkrh-card';
          li.innerHTML = `
            <div class="title">${user.displayName || user.uid}</div>
            <div class="talkrh-meta">${user.uid}</div>
          `;
          membersList.appendChild(li);
        });
      }

  if (saveTalkChannelBtn && talkChannelSelect) {
    saveTalkChannelBtn.addEventListener('click', async () => {
      try {
        saveTalkChannelBtn.disabled = true;
        saveTalkChannelBtn.textContent = 'Enregistrement...';
        const form = new FormData();
        form.append('token', talkChannelSelect.value || '');
        await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/talk/channel'), { method: 'POST', body: form });
        saveTalkChannelBtn.textContent = 'Enregistré !';
        setTimeout(() => {
          saveTalkChannelBtn.textContent = 'Enregistrer le canal';
          saveTalkChannelBtn.disabled = false;
        }, 2000);
      } catch (e) {
        saveTalkChannelBtn.textContent = 'Erreur';
        setTimeout(() => {
          saveTalkChannelBtn.textContent = 'Enregistrer le canal';
          saveTalkChannelBtn.disabled = false;
        }, 2000);
      }
    });
  }
    } catch (e) {
      membersList.innerHTML = '<li>Erreur de chargement des membres.</li>';
    }
  }

  try {
    // Load current group
    const currentRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group'));
    const currentData = await currentRes.json();
    const currentGroupId = currentData.groupId || '';

    // Load all groups
    const groupsRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/groups'));
    const groupsData = await groupsRes.json();
    const groups = Array.isArray(groupsData.groups) ? groupsData.groups : [];

    groupSelect.innerHTML = '';
    groups.forEach(group => {
      const option = document.createElement('option');
      option.value = group.id;
      option.textContent = group.displayName || group.id;
      groupSelect.appendChild(option);
    });

    if (currentGroupId && groups.some(g => g.id === currentGroupId)) {
      groupSelect.value = currentGroupId;
    }

    groupSelect.addEventListener('change', () => {
      if (groupSelect.value) {
        loadMembers(groupSelect.value);
      }
    });

    if (groupSelect.value) {
      await loadMembers(groupSelect.value);
    }

    // Load Talk setting
    try {
      const talkRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/talk'));
      const talkData = await talkRes.json();
      if (talkToggle) talkToggle.checked = !!talkData.talkEnabled;
    } catch (e) {
      // ignore
    }

    // Load Talk channels and current selection
    try {
      if (talkChannelSelect) {
        // Load channels
        const chanRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/talk/channels'));
        const chanData = await chanRes.json();
        const channels = Array.isArray(chanData.channels) ? chanData.channels : [];
        talkChannelSelect.innerHTML = '';
        const noneOpt = document.createElement('option');
        noneOpt.value = '';
        noneOpt.textContent = '— Aucun —';
        talkChannelSelect.appendChild(noneOpt);
        channels.forEach(ch => {
          const opt = document.createElement('option');
          opt.value = ch.token;
          opt.textContent = ch.name || ch.token;
          talkChannelSelect.appendChild(opt);
        });
        // Load current selection
        const curRes = await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/talk/channel'));
        const curData = await curRes.json();
        if (curData && typeof curData.token === 'string') {
          talkChannelSelect.value = curData.token;
        }
      }
    } catch (e) {
      if (talkChannelSelect) {
        talkChannelSelect.innerHTML = '<option>Erreur de chargement</option>';
      }
    }

    saveBtn.addEventListener('click', async () => {
      try {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Enregistrement...';
        const form = new FormData();
        form.append('groupId', groupSelect.value);
        await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group'), { method: 'POST', body: form });
        saveBtn.textContent = 'Enregistré !';
        setTimeout(() => {
          saveBtn.textContent = 'Enregistrer';
          saveBtn.disabled = false;
        }, 2000);
      } catch (e) {
        saveBtn.textContent = 'Erreur';
        setTimeout(() => {
          saveBtn.textContent = 'Enregistrer';
          saveBtn.disabled = false;
        }, 2000);
      }
    });

  } catch (e) {
    groupSelect.innerHTML = '<option>Erreur de chargement</option>';
    membersList.innerHTML = '<li>Erreur de chargement de la configuration.</li>';
  }

  if (saveTalkBtn && talkToggle) {
    saveTalkBtn.addEventListener('click', async () => {
      try {
        saveTalkBtn.disabled = true;
        saveTalkBtn.textContent = 'Enregistrement...';
        const form = new FormData();
        form.append('enabled', talkToggle.checked ? '1' : '0');
        await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/talk'), { method: 'POST', body: form });
        saveTalkBtn.textContent = 'Enregistré !';
        setTimeout(() => {
          saveTalkBtn.textContent = 'Sauvegarder';
          saveTalkBtn.disabled = false;
        }, 2000);
      } catch (e) {
        saveTalkBtn.textContent = 'Erreur';
        setTimeout(() => {
          saveTalkBtn.textContent = 'Sauvegarder';
          saveTalkBtn.disabled = false;
        }, 2000);
      }
    });
  }
});

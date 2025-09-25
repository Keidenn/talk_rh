<?php
script('talk_rh', 'admin');
script('talk_rh', 'navigation');
style('files', 'style');
style('talk_rh', 'main');

// Set navigation context
$_['currentPage'] = 'settings';
?>
<div class="talkrh-layout">
  <?php include_once __DIR__ . '/navigation.php'; ?>
  <div class="talkrh-main">
    <div class="talkrh-container">
      <div class="talkrh-header">
        <h2>Paramètres de l'application</h2>
      </div>
      
      <div class="talkrh-card">
        <div class="title">Groupe administrateur</div>
        <div class="talkrh-meta">Sélectionnez le groupe Nextcloud qui aura les droits d'administration sur cette application.</div>
        
        <div class="field">
          <label for="settingsGroupSelect">Groupe admin</label>
          <select id="settingsGroupSelect" class="">
            <option value="">Chargement...</option>
          </select>
        </div>
        
        <h4>Membres du groupe</h4>
        <ul id="settingsMembersList" class="talkrh-list">
          <li>Chargement...</li>
        </ul>
        
        <div class="talkrh-actions">
          <button id="saveSettingsBtn" class="button primary">Enregistrer</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const groupSelect = document.getElementById('settingsGroupSelect');
  const membersList = document.getElementById('settingsMembersList');
  const saveBtn = document.getElementById('saveSettingsBtn');
  
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
});
</script>

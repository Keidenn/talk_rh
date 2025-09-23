<?php
script('core', 'jquery');
?>
<div class="section">
  <h2>Paramètres Demande de congés</h2>
  <label>Groupe autorisé en tant qu'admin de l'app:
    <select id="groupSelect"></select>
  </label>
  <button id="saveGroup">Enregistrer</button>
</div>
<script>
(async function() {
  const groups = <?php echo json_encode($_['groups'] ?? []); ?>;
  const selected = <?php echo json_encode($_['selected'] ?? 'admin'); ?>;
  const sel = document.getElementById('groupSelect');
  groups.forEach(g => {
    const opt = document.createElement('option');
    opt.value = g.id; opt.textContent = g.displayname + ' (' + g.id + ')';
    if (g.id === selected) opt.selected = true;
    sel.appendChild(opt);
  });
  document.getElementById('saveGroup').addEventListener('click', async () => {
    const form = new FormData();
    form.append('groupId', sel.value);
    await fetch(OC.generateUrl('/apps/talk_rh/api/admin/settings/group'), { method: 'POST', body: form });
    OC.Notification.showTemporary('Groupe admin sauvegardé');
  });
})();
</script>

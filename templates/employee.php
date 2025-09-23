<?php
script('talk_rh', 'employee');
style('files', 'style');
style('talk_rh', 'main');
?>
<div class="talkrh-container">
  <div class="talkrh-header">
    <h2>Mes demandes de congés</h2>
  </div>
  <div id="leave-form" class="talkrh-form">
    <div class="field">
      <label for="startDate">Date de début</label>
      <input type="date" id="startDate">
    </div>
    <div class="field">
      <label for="endDate">Date de fin</label>
      <input type="date" id="endDate">
    </div>
    <div class="field">
      <label for="type">Type</label>
      <select id="type">
        <option value="paid">Payé</option>
        <option value="unpaid">Non payé</option>
        <option value="sick">Maladie</option>
      </select>
    </div>
    <div class="field">
      <label for="reason">Raison (optionnel)</label>
      <input type="text" id="reason" placeholder="Optionnel">
    </div>
    <button id="createBtn" class="button primary">Créer</button>
  </div>
  <div class="talkrh-card" id="icsSection">
    <div class="title">Synchronisation Calendrier</div>
    <div class="field">
      <label for="icsUrl">URL ICS (lecture seule)</label>
      <input type="text" id="icsUrl" readonly value="Chargement…">
    </div>
    <div class="talkrh-actions">
      <button id="copyIcsUrl" class="button">Copier</button>
      <button id="regenIcsToken" class="button danger">Regénérer le lien</button>
    </div>
    <div class="talkrh-meta">Ajoutez ce lien en abonnement ICS dans l'application Calendrier de Nextcloud pour voir vos congés approuvés.</div>
  </div>
  <ul id="myLeaves" class="talkrh-list"></ul>
  <div class="talkrh-empty" id="empEmptyHint" style="display:none;">Aucune demande pour le moment.</div>
</div>


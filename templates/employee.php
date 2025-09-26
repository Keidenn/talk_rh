<?php
script('talk_rh', 'employee');
script('talk_rh', 'navigation');
style('files', 'style');
style('talk_rh', 'main');

// Set navigation context
$_['currentPage'] = 'employee';
?>
<div class="talkrh-layout">
  <?php include_once __DIR__ . '/navigation.php'; ?>
  <div class="talkrh-main">
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
        <option value="paid">Soldé</option>
        <option value="unpaid">Sans Solde</option>
        <option value="sick">Anticipé</option>
      </select>
    </div>
    <div class="field" id="onBehalfField" style="display:none;">
      <label for="onBehalf">Au nom de</label>
      <select id="onBehalf">
        <option value="">Moi-même</option>
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
  <div class="talkrh-actions" style="justify-content: space-between; align-items:center; margin: 8px 0;">
    <div>
      <label for="empPageSize" style="font-size:13px; color: var(--talkrh-muted);">Affichage:</label>
      <select id="empPageSize">
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="15">15</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>
    <div>
      <button id="empPrev" class="button">Précédent</button>
      <span id="empPageInfo" class="talkrh-meta" style="margin: 0 8px;">Page 1/1</span>
      <button id="empNext" class="button">Suivant</button>
    </div>
  </div>
  <ul id="myLeaves" class="talkrh-list"></ul>
  <div class="talkrh-empty" id="empEmptyHint" style="display:none;">Aucune demande pour le moment.</div>
    </div>
  </div>
</div>

<div class="talkrh-modal-backdrop" id="talkrhModalBackdrop" style="display:none;">
  <div class="talkrh-modal" role="dialog" aria-modal="true" aria-labelledby="talkrhModalTitle">
    <div class="talkrh-modal-header">
      <h3 id="talkrhModalTitle"></h3>
      <button id="talkrhModalClose" class="button">Fermer</button>
    </div>
    <div id="talkrhModalBody"></div>
  </div>
  </div>

 


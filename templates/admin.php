<?php
script('talk_rh', 'admin');
style('files', 'style');
style('talk_rh', 'main');
?>
<div class="talkrh-layout">
  <?php if (!empty($_['isAdmin'])): ?>
  <nav class="app-navigation">
    <ul class="app-navigation__list app-navigation-list">
      <li class="app-navigation-entry">
        <a class="app-navigation-entry__link" href="#">Vue admin</a>
        <ul class="app-navigation__children">
          <li class="app-navigation-entry"><a id="navViewCalendar" class="app-navigation-entry__link" href="#">Vue calendrier</a></li>
          <li class="app-navigation-entry"><a id="navViewList" class="app-navigation-entry__link" href="#">Vue liste</a></li>
        </ul>
      </li>
      <li class="app-navigation-entry">
        <a class="app-navigation-entry__link" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('talk_rh.page.employeeView')); ?>">Vue employé</a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
  <div class="talkrh-main">
    <div class="talkrh-container">
  <div class="talkrh-header">
    <h2>Gestion des demandes de congés</h2>
    <div class="talkrh-actions">
      <select id="filterStatus">
        <option value="ALL">Tous les statuts</option>
        <option value="pending">En attente</option>
        <option value="approved">Approuvées</option>
        <option value="rejected">Refusées</option>
      </select>
      <select id="filterUser">
        <option value="ALL">Tous les employés</option>
      </select>
      <button id="openSettings" class="button"><span class="icon-settings"></span></button>
    </div>
  </div>
  <div class="talkrh-calendar">
    <div class="calendar-toolbar">
      <button id="prevMonth" class="button">Mois précédent</button>
      <div class="month-label" id="monthLabel"></div>
      <button id="nextMonth" class="button">Mois suivant</button>
    </div>
    <div class="calendar-weekdays">
      <div class="weekday">L</div>
      <div class="weekday">M</div>
      <div class="weekday">M</div>
      <div class="weekday">J</div>
      <div class="weekday">V</div>
      <div class="weekday">S</div>
      <div class="weekday">D</div>
    </div>
    <div class="calendar-grid" id="calendarGrid"></div>
  </div>
  <div id="adminListView" class="talkrh-list" style="display:none;">
    <div class="talkrh-card">
      <div class="title">Liste des demandes</div>
      <div class="talkrh-meta">Filtrée par Employé et Statut.</div>
      <div class="talkrh-table-wrapper">
        <table id="adminListTable" class="talkrh-table" style="width:100%">
          <thead>
            <tr>
              <th>#</th>
              <th>Employé</th>
              <th>Du</th>
              <th>Au</th>
              <th>Type</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
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
    </div>
  </div>
</div>

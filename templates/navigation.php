<?php
// Navigation template for TalkRH app
// Usage: include this file in your main templates
$isAdmin = $_['isAdmin'] ?? false;
$currentPage = $_['currentPage'] ?? 'admin';
?>

<button class="talkrh-nav-toggle" aria-label="Toggle navigation">☰</button>
<nav class="talkrh-navigation">
  <ul class="app-navigation__list app-navigation-list">
    <?php if ($isAdmin): ?>
      <li class="app-navigation-entry-wrapper app-navigation-entry--collapsible app-navigation-entry--opened">
        <div class="app-navigation-entry <?php if ($currentPage === 'admin') echo 'active'; ?>">
          <a class="app-navigation-entry-link" href="/apps/talk_rh/page">
            <div class="app-navigation-entry-icon">
              <span class="icon-category-office<?php echo ($currentPage === 'admin') ? '-white' : '-dark'; ?>"></span>
            </div>
            <span class="app-navigation-entry__name">Vue admin</span>
          </a>
        </div>
        <ul class="app-navigation-entry__children">
          <li class="app-navigation-entry-wrapper">
            <div class="app-navigation-entry" id="nav-calendar">
              <a id="navViewCalendar" class="app-navigation-entry-link" href="/apps/talk_rh/page?view=calendar" data-view="calendar">
                <div class="app-navigation-entry-icon">
                  <span class="icon-calendar-dark"></span>
                </div>
                <span class="app-navigation-entry__name">Vue calendrier</span>
              </a>
            </div>
          </li>
          <li class="app-navigation-entry-wrapper">
            <div class="app-navigation-entry" id="nav-list">
              <a id="navViewList" class="app-navigation-entry-link" href="/apps/talk_rh/page?view=list" data-view="list">
                <div class="app-navigation-entry-icon">
                  <span class="icon-toggle-filelist-dark"></span>
                </div>
                <span class="app-navigation-entry__name">Vue liste</span>
              </a>
            </div>
          </li>
        </ul>
      </li>
      <li class="app-navigation-entry-wrapper">
        <div class="app-navigation-entry <?php if ($currentPage === 'employee') echo 'active'; ?>">
          <a class="app-navigation-entry-link" href="/apps/talk_rh/page/employee">
            <div class="app-navigation-entry-icon">
              <span class="icon-user<?php echo ($currentPage === 'employee') ? '-white' : '-dark'; ?>"></span>
            </div>
            <span class="app-navigation-entry__name">Vue employé</span>
          </a>
        </div>
      </li>
    <?php else: ?>
      <li class="app-navigation-entry-wrapper">
        <div class="app-navigation-entry <?php if ($currentPage === 'employee') echo 'active'; ?>">
          <a class="app-navigation-entry-link" href="#">
            <div class="app-navigation-entry-icon">
              <span class="icon-user<?php echo ($currentPage === 'employee') ? '-white' : '-dark'; ?>"></span>
            </div>
            <span class="app-navigation-entry__name">Mes congés</span>
          </a>
        </div>
      </li>
    <?php endif; ?>
  </ul>
  
  <?php if ($isAdmin): ?>
  <div class="app-navigation-settings">
    <div class="app-navigation-entry-wrapper">
      <div class="app-navigation-entry <?php if ($currentPage === 'settings') echo 'active'; ?>">
        <a class="app-navigation-entry-link" href="/apps/talk_rh/page/settings">
          <div class="app-navigation-entry-icon">
            <span class="icon-settings<?php echo ($currentPage === 'settings') ? '-white' : '-dark'; ?>"></span>
          </div>
          <span class="app-navigation-entry__name">Paramètres</span>
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</nav>

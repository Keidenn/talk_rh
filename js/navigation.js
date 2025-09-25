(function() {
  'use strict';

  // Navigation management for TalkRH app
  const Navigation = {
    init: function() {
      this.setupMobileToggle();
      this.setupActiveStates();
      this.setupAdminViewToggle();
    },

    setupMobileToggle: function() {
      const toggle = document.querySelector('.talkrh-nav-toggle');
      const nav = document.querySelector('.talkrh-navigation');
      
      if (!toggle || !nav) return;

      // Toggle navigation on button click
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        nav.classList.toggle('show');
      });

      // Close navigation when clicking outside on mobile
      document.addEventListener('click', function(event) {
        if (window.innerWidth <= 1024) {
          if (!nav.contains(event.target) && !toggle.contains(event.target)) {
            nav.classList.remove('show');
          }
        }
      });

      // Close navigation on window resize if going to desktop
      window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
          nav.classList.remove('show');
        }
      });
    },

    setupActiveStates: function() {
      const currentPath = window.location.pathname;
      
      // Wait a bit to ensure DOM is ready and avoid conflicts
      setTimeout(() => {
        if (currentPath.includes('/page/employee')) {
          this.setMainNavActive('employee');
        } else if (currentPath.includes('/page/settings')) {
          this.setMainNavActive('settings');
        } else if (currentPath.includes('/apps/talk_rh/page')) {
          this.setMainNavActive('admin');
        }
      }, 200);
    },

    setMainNavActive: function(page) {
      // Clear main navigation active states only (not sub-menus)
      document.querySelectorAll('.talkrh-navigation .app-navigation-entry.active').forEach(el => {
        // Don't remove active from sub-menu items
        if (!el.closest('.app-navigation-entry__children')) {
          el.classList.remove('active');
        }
      });

      // Set active based on page with more specific selectors
      let selector;
      if (page === 'employee') {
        selector = '.app-navigation-entry-link[href*="/page/employee"]';
      } else if (page === 'settings') {
        selector = '.app-navigation-entry-link[href*="/page/settings"]';
      } else if (page === 'admin') {
        selector = '.app-navigation-entry-link[href="/apps/talk_rh/page"]';
      }

      if (selector) {
        const entry = document.querySelector(selector);
        if (entry && !entry.closest('.app-navigation-entry__children')) {
          const navEntry = entry.closest('.app-navigation-entry');
          if (navEntry) {
            navEntry.classList.add('active');
          }
        }
      }
    },

    setupAdminViewToggle: function() {
      // Only setup if we're on admin page
      if (!window.location.pathname.includes('/apps/talk_rh/page') || 
          window.location.pathname.includes('/page/employee') || 
          window.location.pathname.includes('/page/settings')) {
        return;
      }

      const calendarBtn = document.getElementById('navViewCalendar');
      const listBtn = document.getElementById('navViewList');
      
      if (calendarBtn && listBtn) {
        calendarBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.setAdminViewActive('calendar');
        });

        listBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.setAdminViewActive('list');
        });
      }
    },

    setAdminViewActive: function(view) {
      // Remove active from sub-items
      document.querySelectorAll('#nav-calendar, #nav-list').forEach(el => {
        el.classList.remove('active');
      });
      
      // Add active to selected view
      const targetEl = document.getElementById('nav-' + view);
      if (targetEl) {
        targetEl.classList.add('active');
      }
    }
  };

  // Initialize when DOM is ready with a small delay to avoid conflicts
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(() => {
        Navigation.init();
      }, 100);
    });
  } else {
    setTimeout(() => {
      Navigation.init();
    }, 100);
  }

  // Export for global access if needed
  window.TalkRHNavigation = Navigation;
})();

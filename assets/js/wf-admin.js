(function () {
  'use strict';

  function initResetConfirm() {
    var buttons = document.querySelectorAll('.wf-admin-reset-btn');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        var message = btn.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initResetConfirm);
  } else {
    initResetConfirm();
  }
})();

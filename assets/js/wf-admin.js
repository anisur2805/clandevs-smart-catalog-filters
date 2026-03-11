(function () {
  'use strict';

  function initResetConfirm() {
    var resetButton = document.querySelector('.wf-admin-reset-btn');
    if (!resetButton) {
      return;
    }

    resetButton.addEventListener('click', function (event) {
      var message = resetButton.getAttribute('data-confirm');
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initResetConfirm);
  } else {
    initResetConfirm();
  }
})();

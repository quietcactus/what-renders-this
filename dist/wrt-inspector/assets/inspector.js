(() => {
  'use strict';

  const PANEL_ID = 'wrt-inspector-panel';
  const NODE_ID = 'wp-admin-bar-wrt-inspector';

  const copy = (text) => {
    if (window.navigator.clipboard && window.isSecureContext) {
      return window.navigator.clipboard.writeText(text);
    }

    // Staging is often plain http, where the async clipboard API is unavailable.
    return new Promise((resolve, reject) => {
      const scratch = document.createElement('textarea');
      scratch.value = text;
      scratch.setAttribute('readonly', '');
      scratch.style.position = 'fixed';
      scratch.style.top = '-1000px';
      document.body.appendChild(scratch);
      scratch.select();

      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        ok = false;
      }

      document.body.removeChild(scratch);
      ok ? resolve() : reject(new Error('copy failed'));
    });
  };

  const flash = (button, message) => {
    const original = button.textContent;
    button.textContent = message;
    window.setTimeout(() => {
      button.textContent = original;
    }, 1200);
  };

  const copyFrom = (button, text) => {
    copy(text).then(
      () => flash(button, 'copied'),
      () => flash(button, 'failed')
    );
  };

  const init = () => {
    const panel = document.getElementById(PANEL_ID);
    if (!panel) {
      return;
    }

    const node = document.getElementById(NODE_ID);
    if (node) {
      node.addEventListener('click', (event) => {
        event.preventDefault();
        panel.classList.toggle('is-open');
      });
    }

    panel.addEventListener('click', (event) => {
      const target = event.target;
      if (!target || !target.closest) {
        return;
      }

      if (target.closest('.wrt-insp-close')) {
        panel.classList.remove('is-open');
        return;
      }

      const mini = target.closest('[data-wrt-copy]');
      if (mini) {
        copyFrom(mini, mini.getAttribute('data-wrt-copy'));
        return;
      }

      const context = target.closest('.wrt-insp-copy-context');
      if (context) {
        const report = panel.querySelector('.wrt-insp-report');
        if (report) {
          copyFrom(context, report.value);
        }
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        panel.classList.remove('is-open');
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

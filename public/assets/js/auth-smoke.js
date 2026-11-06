(() => {
  'use strict';

  const stage = document.getElementById('auth-form-stage');
  if (!stage) return;
  const switcher = document.querySelector('.auth-mode-switch');

  const panels = {
    login: document.getElementById('auth-panel-login'),
    register: document.getElementById('auth-panel-register')
  };
  const tabs = Array.from(document.querySelectorAll('[data-auth-mode]'));
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const siteTitle = document.title.split('|').pop().trim() || 'LocalGuide';
  let activeMode = stage.dataset.activeMode === 'register' ? 'register' : 'login';
  let switching = false;
  let switchTimer = null;

  const getNext = () => {
    const loginNext = document.querySelector('[data-auth-form="login"] input[name="next"]');
    const registerNext = document.querySelector('[data-auth-form="register"] input[name="next"]');
    return (loginNext && loginNext.value) || (registerNext && registerNext.value) || '/account.php';
  };

  function syncAddress(mode) {
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('mode', mode);
      url.searchParams.set('next', getNext());
      if (mode === 'register') {
        const roleSelect = document.querySelector('[data-auth-form="register"] select[name="role"]');
        url.searchParams.set('role', roleSelect ? roleSelect.value : 'member');
      } else {
        url.searchParams.delete('role');
      }
      window.history.replaceState({ authMode: mode }, '', url.pathname + url.search + url.hash);
    } catch (_) {
      // The forms still work as standard POST forms if History API is unavailable.
    }
  }

  function setPanel(mode) {
    activeMode = mode;
    stage.dataset.activeMode = mode;
    if (switcher) switcher.dataset.activeMode = mode;
    document.title = (mode === 'register' ? 'Create account' : 'Sign in') + ' | ' + siteTitle;
    Object.entries(panels).forEach(([key, panel]) => {
      if (!panel) return;
      const active = key === mode;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
      panel.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    tabs.forEach((tab) => {
      const active = tab.dataset.authMode === mode;
      tab.classList.toggle('is-active', active);
      if (tab.getAttribute('role') === 'tab') tab.setAttribute('aria-selected', active ? 'true' : 'false');
      if (tab.hasAttribute('role') && tab.getAttribute('role') === 'tab') tab.tabIndex = active ? 0 : -1;
    });
  }

  function switchMode(nextMode) {
    if (!['login', 'register'].includes(nextMode) || nextMode === activeMode || switching) return;
    switching = true;
    if (switchTimer) window.clearTimeout(switchTimer);

    stage.dataset.transition = nextMode;
    stage.classList.add('is-vaporizing');
    stage.setAttribute('aria-busy', 'true');
    tabs.forEach((tab) => { tab.disabled = true; });

    const duration = reduceMotion ? 40 : 500;
    const total = reduceMotion ? 100 : 1100;

    switchTimer = window.setTimeout(() => {
      setPanel(nextMode);
      syncAddress(nextMode);
      const panel = panels[nextMode];
      const firstInput = panel && panel.querySelector('input:not([type="hidden"]), select');
      if (firstInput && window.innerWidth > 640) firstInput.focus({ preventScroll: true });
    }, duration);

    window.setTimeout(() => {
      stage.classList.remove('is-vaporizing');
      stage.setAttribute('aria-busy', 'false');
      tabs.forEach((tab) => { tab.disabled = false; });
      switching = false;
    }, total);
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => switchMode(tab.dataset.authMode));
    if (tab.getAttribute('role') === 'tab') {
      tab.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        const targetMode = activeMode === 'login' ? 'register' : 'login';
        const targetTab = tabs.find((candidate) => candidate.getAttribute('role') === 'tab' && candidate.dataset.authMode === targetMode);
        if (targetTab) targetTab.focus();
        switchMode(targetMode);
      });
    }
  });

  document.querySelectorAll('.auth-password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.parentElement && button.parentElement.querySelector('input');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      const icon = button.querySelector('i');
      if (icon) icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });
  });

  const roleSelect = document.querySelector('[data-auth-form="register"] select[name="role"]');
  if (roleSelect) roleSelect.addEventListener('change', () => {
    if (activeMode === 'register') syncAddress('register');
  });

  const usernameInput = document.querySelector('[data-auth-form="register"] input[name="username"]');
  if (usernameInput) {
    usernameInput.addEventListener('input', () => {
      if (/\s/.test(usernameInput.value)) {
        usernameInput.value = usernameInput.value.replace(/\s+/g, '_');
      }
    });
  }

  // Forms are rendered server-side in the selected mode, so no-JS fallback remains usable.
  setPanel(activeMode);
})();

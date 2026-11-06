(() => {
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealItems = document.querySelectorAll('[data-reveal]');
  if (!reduceMotion && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -35px 0px' });
    revealItems.forEach((el, index) => {
      const requested = Number(el.dataset.revealDelay || 0);
      el.style.setProperty('--reveal-delay', `${Math.min(requested || (index % 4) * 45, 260)}ms`);
      observer.observe(el);
    });
  } else revealItems.forEach(el => el.classList.add('is-visible'));

  // User-controlled colour theme. Preference stores no personal or authentication data.
  const themeToggle = document.getElementById('themeToggle');
  const themeRoot = document.documentElement;
  const storedTheme = (() => { try { return localStorage.getItem('lg4-theme'); } catch (_) { return null; } })();
  const applyTheme = theme => {
    const dark = theme === 'dark';
    themeRoot.dataset.theme = dark ? 'dark' : 'light';
    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
      themeToggle.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
      themeToggle.innerHTML = dark ? '<i class="fa-regular fa-sun"></i>' : '<i class="fa-regular fa-moon"></i>';
    }
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = dark ? '#101b14' : '#10251c';
  };
  applyTheme(storedTheme === 'dark' ? 'dark' : 'light');
  if (themeToggle) themeToggle.addEventListener('click', () => {
    const next = themeRoot.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    try { localStorage.setItem('lg4-theme', next); } catch (_) {}
  });

  // Count-up figures animate once when visible; they remain readable without JS.
  const counters = document.querySelectorAll('[data-count]');
  const countUp = el => {
    const target = Math.max(0, Number(el.dataset.count || 0));
    if (!Number.isFinite(target) || target > 1000000 || reduceMotion) return;
    const duration = 850, start = performance.now();
    const tick = now => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if ('IntersectionObserver' in window && !reduceMotion) {
    const counterObserver = new IntersectionObserver((entries, obs) => entries.forEach(e => {
      if (e.isIntersecting) { countUp(e.target); obs.unobserve(e.target); }
    }), { threshold: .6 });
    counters.forEach(el => counterObserver.observe(el));
  }

  // Gentle pointer tilt on desktop only; no layout movement or touch handlers.
  if (!reduceMotion && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    document.querySelectorAll('.lg4-category-card,.lg4-listing-card').forEach(card => {
      card.addEventListener('pointermove', event => {
        const r = card.getBoundingClientRect();
        const x = (event.clientX - r.left) / r.width - .5;
        const y = (event.clientY - r.top) / r.height - .5;
        card.style.transform = `perspective(900px) rotateX(${(-y * 1.8).toFixed(2)}deg) rotateY(${(x * 2.1).toFixed(2)}deg) translateY(-2px)`;
      });
      card.addEventListener('pointerleave', () => { card.style.transform = ''; });
    });
  }

  // Copy controls used by collection share links and future share actions.
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-copy-text]');
    if (!button) return;
    const value = button.getAttribute('data-copy-text') || '';
    try {
      await navigator.clipboard.writeText(value);
      const before = button.innerHTML;
      button.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      setTimeout(() => { button.innerHTML = before; }, 1600);
    } catch (_) {
      window.prompt('Copy this link:', value);
    }
  });


  // Lightweight directory type-ahead using the first-party listings API.
  const searchInput = document.getElementById('globalSearch');
  const suggestionList = document.getElementById('globalSearchSuggestions');
  if (searchInput && suggestionList) {
    let timer = 0, controller = null;
    searchInput.addEventListener('input', () => {
      window.clearTimeout(timer);
      const term = searchInput.value.trim();
      if (term.length < 2) { suggestionList.replaceChildren(); return; }
      timer = window.setTimeout(async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        try {
          const response = await fetch(`/api/v1/listings.php?q=${encodeURIComponent(term)}&limit=6`, {headers:{'Accept':'application/json'},signal:controller.signal,credentials:'same-origin'});
          if (!response.ok) return;
          const result = await response.json();
          suggestionList.replaceChildren();
          (result.data || []).forEach(item => {
            const option = document.createElement('option');
            option.value = item.name;
            option.label = `${item.category} · ${item.location}`;
            suggestionList.append(option);
          });
        } catch (error) { if (error.name !== 'AbortError') console.debug('LocalGuide suggestions unavailable.'); }
      }, 360);
    });
  }

  // Progressive Web App install prompt. Browsers decide when installation is available.
  let installEvent = null;
  const installButton = document.getElementById('installAppButton');
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault(); installEvent = event;
    if (installButton) installButton.hidden = false;
  });
  if (installButton) installButton.addEventListener('click', async () => {
    if (!installEvent) return;
    installEvent.prompt();
    await installEvent.userChoice.catch(() => null);
    installEvent = null; installButton.hidden = true;
  });
  window.addEventListener('appinstalled', () => { if (installButton) installButton.hidden = true; });
})();

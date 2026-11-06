(() => {
  const toast = message => {
    let el = document.querySelector('.mg-toast');
    if (!el) { el = document.createElement('div'); el.className = 'mg-toast'; document.body.appendChild(el); }
    el.textContent = message; el.classList.add('visible'); window.setTimeout(() => el.classList.remove('visible'), 2400);
  };
  document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(button.dataset.copy || ''); button.innerHTML = '<i class="fa-solid fa-check"></i>'; toast('Copied to clipboard'); }
    catch { toast('Clipboard access is unavailable in this browser context'); }
  }));
  document.querySelectorAll('[data-copy-target]').forEach(button => button.addEventListener('click', async () => {
    const target = document.getElementById(button.dataset.copyTarget || '');
    if (!target) return;
    try { await navigator.clipboard.writeText(target.value || target.textContent || ''); toast('Prompt copied'); }
    catch { target.focus(); target.select(); toast('Press Ctrl+C to copy the selected prompt'); }
  }));

  // Compare up to four local business listings without storing personal data.
  const boxes = [...document.querySelectorAll('[data-compare-id]')];
  if (boxes.length) {
    const bar = document.createElement('div'); bar.className = 'lg3-compare-bar'; bar.innerHTML = '<span class="compare-count">0 places selected</span><button type="button" class="button button-primary button-small">Compare places</button><button type="button" class="compare-clear">Clear</button>';
    document.body.appendChild(bar);
    const label = bar.querySelector('.compare-count'); const go = bar.querySelector('.button');
    const update = () => {
      const selected = boxes.filter(x => x.checked);
      label.textContent = `${selected.length} place${selected.length === 1 ? '' : 's'} selected`;
      bar.classList.toggle('visible', selected.length > 0); go.disabled = selected.length < 2;
      boxes.forEach(x => { if (!x.checked) x.disabled = selected.length >= 4; });
    };
    boxes.forEach(x => x.addEventListener('change', () => { if (boxes.filter(y => y.checked).length > 4) { x.checked = false; toast('Compare up to four places at a time'); } update(); }));
    go.addEventListener('click', () => { const ids = boxes.filter(x => x.checked).map(x => x.dataset.compareId); if (ids.length >= 2) location.href = `compare.php?ids=${encodeURIComponent(ids.join(','))}`; });
    bar.querySelector('.compare-clear').addEventListener('click', () => { boxes.forEach(x => { x.checked = false; x.disabled = false; }); update(); });
    update();
  }

  // Browser-native speech recognition; requires a supported browser and permission.
  document.querySelectorAll('[data-voice-search]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.voiceSearch || '');
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!input || !SpeechRecognition) { toast('Voice search is not supported in this browser.'); return; }
    const recognition = new SpeechRecognition(); recognition.lang = document.documentElement.lang || 'en-IN'; recognition.interimResults = false; recognition.maxAlternatives = 1;
    button.disabled = true; const old = button.innerHTML; button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Listening…';
    recognition.onresult = event => { input.value = event.results[0][0].transcript; input.form?.requestSubmit(); };
    recognition.onerror = () => toast('Voice search could not start. Check microphone permission.');
    recognition.onend = () => { button.disabled = false; button.innerHTML = old; };
    try { recognition.start(); } catch { button.disabled = false; button.innerHTML = old; toast('Voice search is already active.'); }
  }));
})();

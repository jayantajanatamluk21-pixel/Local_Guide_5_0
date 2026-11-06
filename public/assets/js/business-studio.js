(() => {
  const form = document.getElementById('lg5-copy-form');
  if (!form) return;

  const output = document.getElementById('lg5-copy-output');
  const result = document.getElementById('copy-result');
  const generateBtn = document.getElementById('generateBtn');
  const providerBadge = document.getElementById('copy-provider-badge');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const opt = document.getElementById('copy-listing').selectedOptions[0];
    if (!opt) return;

    const name = opt.dataset.name || '';
    const category = opt.dataset.category || '';
    const location = opt.dataset.location || '';
    const description = opt.dataset.description || '';
    const tags = opt.dataset.tags || '';
    const extra = document.getElementById('copy-extra')?.value || '';
    const format = document.getElementById('copy-format')?.value || 'seo_desc';
    const tone = document.getElementById('copy-tone')?.value || 'friendly';

    const details = [description, tags ? `Tags: ${tags}` : '', extra ? `Highlights: ${extra}` : ''].filter(Boolean).join('. ');

    const origBtnHtml = generateBtn.innerHTML;
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating with AI…';

    try {
      const formData = new URLSearchParams();
      formData.append('action', 'generate-copy');
      formData.append('name', name);
      formData.append('category', category);
      formData.append('location', location);
      formData.append('details', details);
      formData.append('format', format);
      formData.append('tone', tone);

      const res = await fetch('/api/v1/ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
      });

      const json = await res.json();
      if (json.status === 'ok' && json.data && json.data.text) {
        result.value = json.data.text;
        providerBadge.textContent = 'Engine: ' + (json.data.provider || 'AI');
        output.hidden = false;
        result.focus();
      } else {
        throw new Error(json.message || 'Generation failed');
      }
    } catch (err) {
      console.warn('API generation failed, falling back to local template:', err);
      // Smooth fallback
      const intro = tone === 'friendly' 
        ? `Welcome to ${name}, your local ${category.toLowerCase()} destination in ${location}.` 
        : `${name} is proud to serve ${location} with premium ${category.toLowerCase()} services.`;
      result.value = `${intro}\n\n${description || 'Discover what makes our local offerings special.'}\n\nVisit us today or get in touch for details!`;
      providerBadge.textContent = 'Engine: Offline Template';
      output.hidden = false;
      result.focus();
    } finally {
      generateBtn.disabled = false;
      generateBtn.innerHTML = origBtnHtml;
    }
  });

  document.getElementById('copy-result-button')?.addEventListener('click', async () => {
    const btn = document.getElementById('copy-result-button');
    try {
      await navigator.clipboard.writeText(result.value);
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
      setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy to Clipboard'; }, 2000);
    } catch {
      result.select();
      document.execCommand('copy');
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
      setTimeout(() => { btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy to Clipboard'; }, 2000);
    }
  });
})();

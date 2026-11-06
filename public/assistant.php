<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
$pageTitle = 'Local Guide AI Assistant & Workbench';
$pageDescription = 'Generate live AI content for local SEO, translation, business growth, and local travel planning.';
$prompt = '';
$aiResult = '';
$kind = (string)($_POST['kind'] ?? 'seo');
$name = trim((string)($_POST['business_name'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$city = trim((string)($_POST['city'] ?? setting('site_city', '')));
$details = trim((string)($_POST['details'] ?? ''));
$language = trim((string)($_POST['language'] ?? 'Hindi'));
$actionType = (string)($_POST['action_type'] ?? 'prompt');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $safeDetails = text_substr($details, 0, 5000);
    $safeName = text_substr($name, 0, 160);
    $safeCity = text_substr($city, 0, 120);
    $safeCat = text_substr($category, 0, 100);

    $prompt = match ($kind) {
        'translate' => "Act as a professional local-business translator. Translate the following business content into {$language}. Preserve factual claims, contact details, prices, addresses, opening hours and brand names exactly. Do not invent information. Return a fluent, locally natural translation and a short list of terms that need human verification.\n\nBusiness: {$safeName}\nCity: {$safeCity}\nSource content:\n{$safeDetails}",
        'business' => "Act as a practical business growth consultant for a small local business. Review the details below and produce: (1) a clear customer value proposition, (2) 5 profile improvements, (3) 3 ethical local marketing ideas, (4) a customer-service checklist, and (5) 3 measurable 30-day goals. Do not invent reviews, credentials, prices or guarantees.\n\nBusiness: {$safeName}\nCategory: {$safeCat}\nArea: {$safeCity}\nDetails:\n{$safeDetails}",
        'review' => "Analyze the review excerpts below. Separate recurring positive themes from recurring complaints, quote only short phrases, identify sample-size limitations, and propose practical responses. Do not infer facts that are not in the reviews. Treat one-off comments as anecdotes rather than trends.\n\nBusiness: {$safeName}\nReviews / notes:\n{$safeDetails}",
        'itinerary' => "Act as a local trip planner. Create a realistic half-day or full-day itinerary using only the verified places listed below. Group stops by proximity where coordinates or areas are provided. Flag missing hours, travel times or availability rather than guessing. Include a balanced pace and alternatives.\n\nCity: {$safeCity}\nInterests: {$safeDetails}",
        default => "Act as a local SEO specialist. Write a factual, helpful business profile for search visitors. Return: (1) SEO title under 60 characters, (2) meta description around 150 characters, (3) concise H1, (4) 120–180 word description, (5) 5 natural local search phrases, and (6) FAQ suggestions. Avoid keyword stuffing and unsupported claims.\n\nBusiness: {$safeName}\nCategory: {$safeCat}\nLocation: {$safeCity}\nVerified facts and amenities:\n{$safeDetails}"
    };
    $prompt = text_substr($prompt, 0, 9000);

    if ($actionType === 'run_ai') {
        $aiRes = ai_generate_text($prompt, "You are a professional local business advisor and content strategist.");
        $aiResult = $aiRes['text'];
    }
}

$activeProvider = ai_get_active_provider();
$providerLabel = match($activeProvider) {
    'gemini' => 'Google Gemini (Live)',
    'openai' => 'OpenAI GPT (Live)',
    default => 'Smart Local AI Engine'
};

include __DIR__ . '/includes/header.php';
?>

<main id="main" class="container account-page">
  <div class="page-intro">
    <span class="eyebrow">AI POWERED WORKBENCH</span>
    <h1>Local Guide AI Assistant</h1>
    <p>Generate high-quality local business content, review sentiment breakdowns, and itineraries. Active Engine: <strong><?= h($providerLabel) ?></strong>.</p>
  </div>

  <div class="lg3-ai-layout">
    <section class="content-panel">
      <form method="post" class="studio-fields">
        <?= csrf_field() ?>
        <input type="hidden" name="action_type" id="actionTypeInput" value="run_ai">

        <label>Assistant task
          <select name="kind">
            <option value="seo" <?= $kind==='seo'?'selected':'' ?>>Local SEO content writer</option>
            <option value="translate" <?= $kind==='translate'?'selected':'' ?>>Business content translator</option>
            <option value="business" <?= $kind==='business'?'selected':'' ?>>Business growth assistant</option>
            <option value="review" <?= $kind==='review'?'selected':'' ?>>Review theme analysis</option>
            <option value="itinerary" <?= $kind==='itinerary'?'selected':'' ?>>Local itinerary planner</option>
          </select>
        </label>

        <label>Business / project name
          <input name="business_name" maxlength="160" value="<?= h($name) ?>" placeholder="e.g. Blue Lagoon Cafe">
        </label>

        <div class="form-grid">
          <label>Category
            <input name="category" maxlength="100" value="<?= h($category) ?>" placeholder="e.g. Food & Cafes">
          </label>
          <label>City / neighbourhood
            <input name="city" maxlength="120" value="<?= h($city) ?>" placeholder="e.g. Park Street, Kolkata">
          </label>
        </div>

        <label>Target language (translation task)
          <select name="language">
            <option <?= $language==='Hindi'?'selected':'' ?>>Hindi</option>
            <option <?= $language==='Bengali'?'selected':'' ?>>Bengali</option>
            <option <?= $language==='English'?'selected':'' ?>>English</option>
            <option <?= $language==='Marathi'?'selected':'' ?>>Marathi</option>
            <option <?= $language==='Tamil'?'selected':'' ?>>Tamil</option>
            <option <?= $language==='Telugu'?'selected':'' ?>>Telugu</option>
          </select>
        </label>

        <label>Facts, source copy, reviews or requirements
          <textarea name="details" rows="6" maxlength="5000" placeholder="Include details such as amenities, price points, popular items, or questions."><?= h($details) ?></textarea>
        </label>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <button class="button button-primary" type="submit" onclick="document.getElementById('actionTypeInput').value='run_ai';">
            <i class="fa-solid fa-sparkles"></i> Generate with AI
          </button>
          <button class="button button-outline" type="submit" onclick="document.getElementById('actionTypeInput').value='prompt';">
            <i class="fa-solid fa-code"></i> Build Prompt Only
          </button>
        </div>
      </form>
    </section>

    <section class="content-panel">
      <?php if ($aiResult): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
          <span class="eyebrow" style="margin:0;"><i class="fa-solid fa-sparkles"></i> GENERATED AI RESPONSE</span>
          <span class="status-pill status-approved"><?= h($providerLabel) ?></span>
        </div>
        <textarea id="aiOutput" class="lg3-prompt-output" rows="14" readonly style="font-family:inherit;font-size:0.95rem;line-height:1.6;background:#f9fbf9;"><?= h($aiResult) ?></textarea>
        <div class="lg3-actions" style="margin-top:0.75rem;">
          <button class="button button-primary" type="button" data-copy-target="aiOutput"><i class="fa-regular fa-copy"></i> Copy response</button>
        </div>
      <?php endif; ?>

      <?php if ($prompt): ?>
        <div style="margin-top:1.5rem;">
          <span class="eyebrow">RAW PROMPT SPECIFICATION</span>
          <textarea id="promptOutput" class="lg3-prompt-output" rows="8" readonly style="font-size:0.85rem;"><?= h($prompt) ?></textarea>
          <div class="lg3-actions" style="margin-top:0.5rem;">
            <button class="button button-outline button-small" type="button" data-copy-target="promptOutput">Copy prompt</button>
            <a class="button button-outline button-small" href="https://chatgpt.com/" target="_blank" rel="noopener">Open ChatGPT <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
          </div>
        </div>
      <?php elseif (!$aiResult): ?>
        <div class="empty-state">
          <span><i class="fa-solid fa-wand-magic-sparkles"></i></span>
          <h3>Ready to create content</h3>
          <p>Fill in the details on the left and click <strong>Generate with AI</strong> for instant tailored results.</p>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<script>
document.querySelectorAll('[data-copy-target]').forEach(b => {
  b.addEventListener('click', () => {
    const el = document.getElementById(b.dataset.copyTarget);
    if (!el) return;
    navigator.clipboard?.writeText(el.value).then(() => {
      const orig = b.innerHTML;
      b.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
      setTimeout(() => b.innerHTML = orig, 2000);
    });
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_owner();

$uid = current_user_id();
$s = db()->prepare('SELECT id, name, category, location, description, tags FROM listings WHERE owner_user_id=:u ORDER BY name');
$s->execute([':u' => $uid]);
$listings = $s->fetchAll();
$ownerTitle = 'AI Business Content Studio';

$activeAi = ai_get_active_provider();
$aiName = match($activeAi) {
    'gemini' => 'Google Gemini (Live)',
    'openai' => 'OpenAI GPT (Live)',
    default => 'Smart Local AI Engine'
};

include __DIR__ . '/_header.php';
?>

<div class="owner-callout">
  <strong><?= h($aiName) ?> Enabled</strong>
  <p>Generate professional SEO descriptions, social media announcements, promotional copy, or polite customer review responses tailored directly to your business profile.</p>
</div>

<div class="admin-panel">
  <div class="panel-heading">
    <div>
      <span class="eyebrow">AI MARKETING WORKBENCH</span>
      <h2>Generate High-Converting Business Copy</h2>
      <p>Select your business, target format, and preferred tone.</p>
    </div>
  </div>

  <?php if ($listings): ?>
    <form class="studio-fields" id="lg5-copy-form">
      <label>Select Business
        <select id="copy-listing" required>
          <?php foreach ($listings as $l): ?>
            <option value="<?= (int)$l['id'] ?>" data-name="<?= h($l['name']) ?>" data-category="<?= h($l['category']) ?>" data-location="<?= h($l['location']) ?>" data-description="<?= h($l['description']) ?>" data-tags="<?= h($l['tags']) ?>">
              <?= h($l['name']) ?> (<?= h($l['category']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="form-grid">
        <label>Content Format
          <select id="copy-format">
            <option value="seo_desc">Local SEO Listing Description</option>
            <option value="social_promo">Social Media & WhatsApp Promo Post</option>
            <option value="reply_positive">Thank-You Reply to Positive Review</option>
            <option value="reply_critical">Constructive Reply to Feedback/Review</option>
          </select>
        </label>
        <label>Tone of Voice
          <select id="copy-tone">
            <option value="friendly">Friendly & Welcoming</option>
            <option value="professional">Professional & Trustworthy</option>
            <option value="luxurious">Premium & Exclusive</option>
            <option value="exciting">Exciting & High Energy</option>
          </select>
        </label>
      </div>

      <label>Specific Highlights / Seasonal Offers (Optional)
        <input id="copy-extra" maxlength="200" placeholder="e.g. 20% off weekend brunch, new vegan menu, outdoor seating">
      </label>

      <button class="button button-primary" type="submit" id="generateBtn">
        <i class="fa-solid fa-sparkles"></i> Generate with AI
      </button>
    </form>

    <div id="lg5-copy-output" class="lg5-copy-output" aria-live="polite" hidden style="margin-top:2rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
        <label for="copy-result" style="margin:0;font-weight:700;">AI Generated Draft</label>
        <span id="copy-provider-badge" class="status-pill status-approved" style="font-size:0.8rem;"></span>
      </div>
      <textarea id="copy-result" rows="9" style="width:100%;line-height:1.6;font-family:inherit;"></textarea>
      <div style="margin-top:0.75rem;display:flex;gap:0.75rem;">
        <button class="button button-outline" type="button" id="copy-result-button">
          <i class="fa-regular fa-copy"></i> Copy to Clipboard
        </button>
      </div>
    </div>
  <?php else: ?>
    <p class="empty-admin">No approved business is assigned to your owner account yet.</p>
  <?php endif; ?>
</div>

<script src="<?= app_path('assets/js/business-studio.js') ?>" defer></script>
<?php include __DIR__ . '/_footer.php'; ?>

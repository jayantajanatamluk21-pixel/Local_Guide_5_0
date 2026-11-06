<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$voucherCode = trim((string)($_GET['voucher'] ?? ''));

// View existing voucher pass
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $voucherCode !== '') {
    $s = db()->prepare("SELECT r.*, o.title offer_title, o.description offer_desc, o.promo_code, o.discount_percent, o.ends_at, l.name listing_name, l.slug listing_slug, l.location, l.city FROM offer_redemptions r JOIN offers o ON o.id=r.offer_id JOIN listings l ON l.id=o.listing_id WHERE r.voucher_code=:vc LIMIT 1");
    $s->execute([':vc' => $voucherCode]);
    $voucher = $s->fetch();

    if (!$voucher) {
        flash('error', 'Voucher not found or expired.');
        redirect(app_path('offers.php'));
    }

    $pageTitle = 'Digital Voucher · ' . $voucher['offer_title'];
    $qrSvg = generate_qr_svg($voucherCode, 200, '#10251c', '#ffffff');
    include __DIR__ . '/includes/header.php';
    ?>
    <main id="main" class="container account-page">
      <div class="page-intro">
        <span class="eyebrow">DIGITAL VOUCHER PASS</span>
        <h1><?= h($voucher['offer_title']) ?></h1>
        <p>Present this digital pass or QR code at the business counter to redeem.</p>
      </div>

      <div class="content-panel" style="max-width:540px;margin:2rem auto;text-align:center;border:2px dashed #146b60;border-radius:18px;padding:2rem;">
        <span class="status-pill <?= $voucher['status']==='redeemed'?'status-rejected':'status-approved' ?>" style="font-size:0.95rem;margin-bottom:1rem;display:inline-block;">
          <?= $voucher['status']==='redeemed' ? 'ALREADY REDEEMED' : 'READY TO REDEEM' ?>
        </span>
        <h2 style="margin:0.5rem 0;"><?= h($voucher['listing_name']) ?></h2>
        <p class="muted" style="margin-bottom:1.5rem;"><i class="fa-solid fa-location-dot"></i> <?= h($voucher['location']) ?><?= $voucher['city']?' · '.h($voucher['city']):'' ?></p>

        <div style="background:#f8faf8;padding:1.5rem;border-radius:14px;margin-bottom:1.5rem;">
          <div style="display:flex;justify-content:center;margin-bottom:1rem;">
            <?= $qrSvg ?>
          </div>
          <small style="display:block;letter-spacing:0.15em;text-transform:uppercase;color:#557065;margin-bottom:0.25rem;">VOUCHER CODE</small>
          <strong style="font-size:1.6rem;letter-spacing:0.1em;color:#10251c;font-family:monospace;"><?= h($voucher['voucher_code']) ?></strong>
          <?php if($voucher['promo_code']): ?>
            <p style="margin:0.5rem 0 0 0;font-size:0.9rem;">Promo Code: <strong><?= h($voucher['promo_code']) ?></strong></p>
          <?php endif; ?>
        </div>

        <p style="font-size:0.95rem;line-height:1.5;"><?= nl2br(h($voucher['offer_desc'])) ?></p>
        <?php if($voucher['ends_at']): ?>
          <small class="muted" style="display:block;margin-top:1rem;"><i class="fa-regular fa-clock"></i> Valid until <?= h(format_date($voucher['ends_at'])) ?></small>
        <?php endif; ?>

        <div style="margin-top:2rem;display:flex;gap:0.75rem;justify-content:center;">
          <a class="button button-outline" href="<?= app_path('index.php?listing='.rawurlencode($voucher['listing_slug'])) ?>">View place</a>
          <button class="button button-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print pass</button>
        </div>
      </div>
    </main>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(app_path('offers.php'));
verify_csrf();

$id = (int)($_POST['offer_id'] ?? 0);
$s = db()->prepare("SELECT o.*, l.slug FROM offers o JOIN listings l ON l.id=o.listing_id WHERE o.id=:id AND o.status='published' AND l.status='published' AND (o.starts_at='' OR o.starts_at<=:now) AND (o.ends_at='' OR o.ends_at>=:today)");
$s->execute([':id' => $id, ':now' => date('Y-m-d H:i:s'), ':today' => date('Y-m-d')]);
$offer = $s->fetch();

if (!$offer) {
    flash('error', 'This offer is not currently active.');
    redirect(app_path('offers.php'));
}

$hash = visitor_hash();
$code = 'LG-' . strtoupper(bin2hex(random_bytes(4)));

try {
    $ins = db()->prepare('INSERT INTO offer_redemptions(offer_id, user_id, visitor_hash, voucher_code, status) VALUES(:o, :u, :v, :code, :st)');
    $ins->execute([
        ':o' => $id,
        ':u' => current_user_id() ?: null,
        ':v' => $hash,
        ':code' => $code,
        ':st' => 'active'
    ]);
    flash('success', 'Digital voucher generated! Present this voucher at the store.');
    redirect(app_path('coupon-redeem.php?voucher=' . urlencode($code)));
} catch (PDOException $e) {
    // If already saved, fetch the existing voucher code
    $exist = db()->prepare('SELECT voucher_code FROM offer_redemptions WHERE offer_id=:o AND visitor_hash=:v LIMIT 1');
    $exist->execute([':o' => $id, ':v' => $hash]);
    $exCode = (string)$exist->fetchColumn();
    if ($exCode !== '') {
        redirect(app_path('coupon-redeem.php?voucher=' . urlencode($exCode)));
    }
    flash('info', 'You have already saved this offer.');
    redirect(app_path('offers.php'));
}

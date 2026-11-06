<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_owner();

$uid = current_user_id();
$ownerTitle = 'Digital Voucher Validator';

// Process validation & redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $code = strtoupper(trim((string)($_POST['voucher_code'] ?? '')));

    if ($action === 'redeem' && $code !== '') {
        // Ensure voucher belongs to an offer on a listing owned by this owner
        $s = db()->prepare("SELECT r.id, r.status, o.title offer_title, l.name listing_name FROM offer_redemptions r JOIN offers o ON o.id=r.offer_id JOIN listings l ON l.id=o.listing_id WHERE r.voucher_code=:code AND l.owner_user_id=:u LIMIT 1");
        $s->execute([':code' => $code, ':u' => $uid]);
        $row = $s->fetch();

        if (!$row) {
            flash('error', "Voucher '{$code}' not found or does not belong to your listings.");
        } elseif ($row['status'] === 'redeemed') {
            flash('warning', "Voucher '{$code}' was ALREADY redeemed.");
        } else {
            db()->prepare("UPDATE offer_redemptions SET status='redeemed', redeemed_at=:now, redeemed_by=:u WHERE id=:id")
                ->execute([':now' => date('Y-m-d H:i:s'), ':u' => $uid, ':id' => $row['id']]);
            flash('success', "Success! Voucher '{$code}' for '{$row['offer_title']}' has been redeemed.");
        }
    }
    redirect(app_path('owner/coupons.php'));
}

// Fetch recent redemptions for this owner's listings
$histStmt = db()->prepare("SELECT r.*, o.title offer_title, o.promo_code, l.name listing_name, u.name user_name, u.email user_email FROM offer_redemptions r JOIN offers o ON o.id=r.offer_id JOIN listings l ON l.id=o.listing_id LEFT JOIN users u ON u.id=r.user_id WHERE l.owner_user_id=:u ORDER BY r.created_at DESC LIMIT 30");
$histStmt->execute([':u' => $uid]);
$vouchers = $histStmt->fetchAll();

include __DIR__ . '/_header.php';
?>

<div class="owner-callout">
  <strong>Instant QR & Voucher Validation</strong>
  <p>When a customer visits your store and presents a LocalGuide digital voucher or QR pass, enter the code below to verify and validate their discount in real-time.</p>
</div>

<div class="admin-panel">
  <div class="panel-heading">
    <div>
      <span class="eyebrow">STORE COUNTER VALIDATOR</span>
      <h2>Redeem Customer Voucher</h2>
    </div>
  </div>
  <form method="post" class="studio-fields" style="max-width:500px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="redeem">
    <label>Voucher Code
      <input name="voucher_code" placeholder="e.g. LG-A1B2C3D4" maxlength="30" required style="font-family:monospace;font-size:1.1rem;letter-spacing:0.05em;text-transform:uppercase;">
    </label>
    <button class="button button-primary" type="submit"><i class="fa-solid fa-check"></i> Validate & Redeem Voucher</button>
  </form>
</div>

<div class="admin-panel" style="margin-top:2rem;">
  <div class="panel-heading">
    <div>
      <span class="eyebrow">REDEMPTION LOG</span>
      <h2>Customer Vouchers for your Listings</h2>
    </div>
  </div>

  <?php if ($vouchers): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Listing / Offer</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Issued</th>
            <th>Redeemed</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vouchers as $v): ?>
            <tr>
              <td><strong style="font-family:monospace;"><?= h($v['voucher_code'] ?: 'Legacy code') ?></strong></td>
              <td>
                <strong><?= h($v['listing_name']) ?></strong>
                <small><?= h($v['offer_title']) ?></small>
              </td>
              <td><?= h($v['user_name'] ?: 'Guest visitor') ?></td>
              <td>
                <span class="status-pill <?= $v['status']==='redeemed'?'status-rejected':'status-approved' ?>">
                  <?= $v['status']==='redeemed' ? 'Redeemed' : 'Active' ?>
                </span>
              </td>
              <td><small><?= h(format_date($v['created_at'])) ?></small></td>
              <td><small><?= $v['redeemed_at'] ? h(format_date($v['redeemed_at'])) : '—' ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="empty-admin">No customer vouchers recorded for your listings yet.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_footer.php'; ?>

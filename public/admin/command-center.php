<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';require_admin();
$counts=[
 'published'=>(int)db()->query("SELECT COUNT(*) FROM listings WHERE status='published'")->fetchColumn(),
 'pending_listings'=>(int)db()->query("SELECT COUNT(*) FROM listings WHERE status='pending'")->fetchColumn(),
 'members'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role IN ('member','owner') AND is_active=1")->fetchColumn(),
 'owners'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role='owner' AND is_active=1")->fetchColumn(),
 'open_enquiries'=>(int)db()->query("SELECT COUNT(*) FROM enquiries WHERE status IN ('new','read')")->fetchColumn(),
 'pending_bookings'=>(int)db()->query("SELECT COUNT(*) FROM bookings WHERE status='requested'")->fetchColumn(),
 'pending_claims'=>(int)db()->query("SELECT COUNT(*) FROM owner_claims WHERE status='pending'")->fetchColumn(),
 'moderation'=>(int)db()->query("SELECT (SELECT COUNT(*) FROM community_posts WHERE status='pending')+(SELECT COUNT(*) FROM reviews WHERE status='pending')+(SELECT COUNT(*) FROM content_reports WHERE status='open')")->fetchColumn(),
 'tasks_open'=>(int)db()->query("SELECT COUNT(*) FROM business_tasks WHERE status<>'done'")->fetchColumn(),
 'security_24h'=>(int)db()->query("SELECT COUNT(*) FROM security_audit_logs WHERE severity IN ('warning','critical') AND created_at>=datetime('now','-24 hours')")->fetchColumn(),
];
$recent=db()->query('SELECT event_key,severity,actor_role,created_at FROM security_audit_logs ORDER BY id DESC LIMIT 10')->fetchAll();
$adminTitle='Command center';include __DIR__.'/_header.php';
?>
<div class="lg5-command-intro"><span class="eyebrow">LOCALGUIDE 5.0 · OPERATIONS</span><h2>One view of the platform.</h2><p>Operational counters are live database counts. Revenue and third-party services are not inferred from test or demo records.</p></div>
<div class="stats-grid lg5-stats-grid">
<?php foreach([['Published places',$counts['published'],'fa-store'],['Pending listings',$counts['pending_listings'],'fa-hourglass-half'],['Active members',$counts['members'],'fa-users'],['Business owners',$counts['owners'],'fa-briefcase'],['Open enquiries',$counts['open_enquiries'],'fa-inbox'],['Booking requests',$counts['pending_bookings'],'fa-calendar-check'],['Owner claims',$counts['pending_claims'],'fa-user-check'],['Items needing moderation',$counts['moderation'],'fa-shield-halved'],['Open CRM tasks',$counts['tasks_open'],'fa-list-check'],['Security alerts · 24h',$counts['security_24h'],'fa-triangle-exclamation']] as [$label,$value,$icon]): ?>
<article class="stat-card lg5-stat"><span><i class="fa-solid <?= h($icon) ?>"></i> <?= h($label) ?></span><strong><?= (int)$value ?></strong></article>
<?php endforeach; ?>
</div>
<div class="lg5-command-grid">
<section class="admin-panel"><div class="panel-heading"><div><span class="eyebrow">ADMIN TOOLING</span><h2>Control surfaces</h2></div></div><div class="lg5-quick-links"><a href="<?= app_path('admin/security-audit.php') ?>"><i class="fa-solid fa-shield-halved"></i><span><b>Security audit</b><small>Authentication and system events</small></span></a><a href="<?= app_path('admin/monetization.php') ?>"><i class="fa-solid fa-coins"></i><span><b>Monetization</b><small>Plans, subscriptions and payment ledger</small></span></a><a href="<?= app_path('admin/integrations.php') ?>"><i class="fa-solid fa-plug"></i><span><b>Integrations</b><small>Credential readiness, never secret values</small></span></a><a href="<?= app_path('admin/system-health.php') ?>"><i class="fa-solid fa-heart-pulse"></i><span><b>System health</b><small>Runtime, database and storage checks</small></span></a></div></section>
<section class="admin-panel"><div class="panel-heading"><div><span class="eyebrow">SECURITY TELEMETRY</span><h2>Recent audit events</h2></div><a class="button button-outline button-small" href="<?= app_path('admin/security-audit.php') ?>">View all</a></div>
<?php if($recent): ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Event</th><th>Severity</th><th>Actor</th><th>Time</th></tr></thead><tbody><?php foreach($recent as $r): ?><tr><td><?= h($r['event_key']) ?></td><td><span class="status-pill <?= $r['severity']==='critical'?'status-rejected':($r['severity']==='warning'?'status-pending':'status-approved') ?>"><?= h(ucfirst($r['severity'])) ?></span></td><td><?= h($r['actor_role']) ?></td><td><?= h($r['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty-admin">Audit events will appear as users and administrators sign in or use protected workflows.</p><?php endif; ?>
</section></div>
<?php include __DIR__.'/_footer.php'; ?>

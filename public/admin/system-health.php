<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_admin();
$adminTitle='System health';
$checks=[];
$checks[]=['PHP runtime',PHP_VERSION,version_compare(PHP_VERSION,'8.2.0','>='),'PHP 8.2 or newer is required.'];
foreach(['pdo_sqlite','sqlite3','fileinfo','openssl'] as $ext) $checks[]=['PHP extension: '.$ext,extension_loaded($ext)?'Enabled':'Missing',extension_loaded($ext),'Enable the extension in the active php.ini.'];
$storage=APP_ROOT.'/storage';$uploads=APP_ROOT.'/public/uploads';
$checks[]=['Application encryption key',app_encryption_key()!==null?'Configured':'Not configured',app_encryption_key()!==null,'Configure APP_ENCRYPTION_KEY to enable encrypted two-factor authentication.'];
$checks[]=['Storage directory',is_dir($storage)&&is_writable($storage)?'Writable':'Not writable',is_dir($storage)&&is_writable($storage),'The application must write its SQLite database and logs.'];
$checks[]=['Uploads directory',is_dir($uploads)&&is_writable($uploads)?'Writable':'Not writable',is_dir($uploads)&&is_writable($uploads),'Business and community image uploads require this directory.'];
$dbCheck='Unavailable';$dbMessage='';
try{$dbCheck=(string)db()->query('PRAGMA quick_check')->fetchColumn();$dbMessage=$dbCheck==='ok'?'SQLite integrity check passed.':'SQLite reported: '.$dbCheck;}catch(Throwable $e){$dbMessage='Database check could not complete.';}
$checks[]=['SQLite integrity',$dbCheck,strtolower($dbCheck)==='ok',$dbMessage];
$version=(string)setting('platform_version','unknown');
$listingCount=(int)db()->query('SELECT COUNT(*) FROM listings')->fetchColumn();
$userCount=(int)db()->query('SELECT COUNT(*) FROM users WHERE role IN (\'member\',\'owner\')')->fetchColumn();
$backupDir=APP_ROOT.'/storage/backups';$backups=is_dir($backupDir)?(glob($backupDir.'/localguide-*.sqlite')?:[]):[];
$pageTitle='System health';include __DIR__.'/_header.php';
?>
<section class="stats-grid"><article class="stat-card"><span>Platform version</span><strong><?= h($version) ?></strong></article><article class="stat-card"><span>Published + draft listings</span><strong><?= $listingCount ?></strong></article><article class="stat-card"><span>Member accounts</span><strong><?= $userCount ?></strong></article><article class="stat-card"><span>Verified backups</span><strong><?= count($backups) ?></strong></article></section>
<section class="content-panel" style="margin-top:22px"><div class="panel-heading"><div><span class="eyebrow">PRE-DEPLOYMENT CHECKS</span><h2>Environment and database health</h2><p>Read-only checks. This page does not modify your system.</p></div><a class="button button-outline button-small" href="<?= app_path('admin/index.php') ?>">Back to dashboard</a></div><div class="table-wrap"><table class="admin-table"><thead><tr><th>Check</th><th>Status</th><th>Result</th></tr></thead><tbody><?php foreach($checks as $c): ?><tr><td><strong><?= h($c[0]) ?></strong><small><?= h($c[3]) ?></small></td><td><span class="status-pill <?= $c[2]?'status-approved':'status-rejected' ?>"><?= $c[2]?'Passed':'Action required' ?></span></td><td><?= h((string)$c[1]) ?></td></tr><?php endforeach; ?></tbody></table></div><div class="owner-callout" style="margin-top:18px"><strong>Database backup</strong><p>Use the included <code>backup-database.bat</code> from the project root. It creates a SQLite snapshot in <code>storage/backups</code>, verifies <code>PRAGMA quick_check</code>, and retains the newest 30 backup files. Copy a backup to a separate drive or secure cloud storage as well.</p><code>backup-database.bat</code></div></section>
<?php include __DIR__.'/_footer.php'; ?>

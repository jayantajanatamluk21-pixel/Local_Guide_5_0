<?php
declare(strict_types=1);if(PHP_SAPI!=='cli'){http_response_code(404);exit("Not found\n");}
require_once dirname(__DIR__).'/app/bootstrap.php';$pdo=db();$checks=[];
$checks[]=['PHP >= 8.2',version_compare(PHP_VERSION,'8.2.0','>=')];
foreach(['pdo_sqlite','sqlite3','fileinfo'] as $ext)$checks[]=['Extension '.$ext,extension_loaded($ext)];
$required=['users','listings','reviews','favorites','events','offers','bookings','enquiries','community_posts','saved_collections','security_audit_logs','business_tasks','membership_plans','business_subscriptions','payment_transactions','integration_registry','feature_flags'];
$tables=$pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);foreach($required as $table)$checks[]=['Table '.$table,in_array($table,$tables,true)];
$integrity=(string)$pdo->query('PRAGMA quick_check')->fetchColumn();$checks[]=['SQLite PRAGMA quick_check',$integrity==='ok'];
$version=(string)$pdo->query("SELECT value FROM settings WHERE key='platform_version'")->fetchColumn();$checks[]=['Platform schema version is 5.0.0',$version==='5.0.0'];
$counts=['users'=>(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),'listings'=>(int)$pdo->query('SELECT COUNT(*) FROM listings')->fetchColumn(),'reviews'=>(int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn()];
$failed=0;echo "LocalGuide 5.0 upgrade verification\n===============================\n";foreach($checks as [$label,$pass]){echo ($pass?'[PASS] ':'[FAIL] ').$label."\n";if(!$pass)$failed++;}echo "\nPreserved records: ".json_encode($counts)."\nSQLite integrity: ".$integrity."\n";exit($failed?1:0);

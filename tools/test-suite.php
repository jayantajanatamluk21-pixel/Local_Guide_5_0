<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit("Not found\n");}
require_once dirname(__DIR__).'/app/bootstrap.php';
$tests=[];$test=static function(string $name,callable $fn)use(&$tests):void{try{$ok=(bool)$fn();$tests[]=[$name,$ok,$ok?'':'Returned false'];}catch(Throwable $e){$tests[]=[$name,false,$e->getMessage()];}};
$test('PHP runtime >= 8.2',fn()=>version_compare(PHP_VERSION,'8.2.0','>='));
$test('PDO SQLite is available',fn()=>extension_loaded('pdo_sqlite')&&extension_loaded('sqlite3'));
$test('SQLite quick_check',fn()=>db()->query('PRAGMA quick_check')->fetchColumn()==='ok');
$test('SQLite foreign_key_check is clean',fn()=>count(db()->query('PRAGMA foreign_key_check')->fetchAll())===0);
$test('5.0 audit table exists',fn()=>(bool)db()->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='security_audit_logs'")->fetchColumn());
$test('5.0 CRM tasks table exists',fn()=>(bool)db()->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='business_tasks'")->fetchColumn());
$test('5.0 payment ledger exists',fn()=>(bool)db()->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='payment_transactions'")->fetchColumn());
$test('Schema version is 5.0.0',fn()=>(string)setting('platform_version','')==='5.0.0');
$test('Safe local return path accepted',fn()=>safe_return_to('/index.php?x=1','account.php')==='/index.php?x=1');
$test('External return path rejected',fn()=>safe_return_to('https://example.invalid','account.php')==='/account.php');
$test('TOTP RFC 6238 SHA-1 test vector',fn()=>totp_code('12345678901234567890',59)==='287082');
$test('TOTP rejects malformed code',fn()=>!verify_totp_code('secret','12ab56',59));
$test('Integration catalogue is available',fn()=>count(integration_catalog())>=6);
$test('AI engine text generation succeeds',function(){
    $res = ai_generate_text('Test prompt', 'Test system');
    return $res['ok'] && !empty($res['text']);
});
$test('AI review summary generator succeeds',function(){
    $res = ai_summarize_listing_reviews(1);
    return $res['ok'] && !empty($res['summary']);
});
$test('Pure PHP QR Code SVG generates valid vector',function(){
    $svg = generate_qr_svg('LG-TEST-VOUCHER', 200);
    return str_starts_with($svg, '<svg') && str_contains($svg, '</svg>');
});
$test('Transactional email dispatcher operates',function(){
    $res = send_transactional_email('test@example.com', 'Tester', 'Test Subject', '<p>Hello</p>', 'Hello');
    return $res['ok'] && in_array($res['provider'], ['resend', 'spool'], true);
});
$failed=0;echo "LocalGuide 5.0 smoke tests\n==========================\n";foreach($tests as [$name,$ok,$error]){echo ($ok?'[PASS] ':'[FAIL] ').$name.($ok?'':' — '.$error)."\n";if(!$ok)$failed++;}echo "\n".count($tests).' tests, '.$failed." failures.\n";exit($failed?1:0);

<?php
$projectRoot=dirname(__DIR__,3);
require_once $projectRoot.'/app/functions.php';
// Load only the private invite secret before bootstrapping the database. Invalid
// guesses can therefore receive a generic 404 even if the database is unavailable.
$envFile=$projectRoot.'/.env';
if(is_file($envFile)&&is_readable($envFile)){
    foreach(file($envFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
        $line=trim((string)$line);
        if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;
        [$key,$value]=explode('=',$line,2);$key=trim($key);$value=trim(trim($value),"\"");
        if($key!==''&&getenv($key)===false)putenv($key.'='.$value);
    }
}
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');
$expected=trim((string)(getenv('ADMIN_INVITE_TOKEN')?:''));
$provided=(string)($_GET['invite']??'');
if(strlen($expected)<32)send_not_found();
$isHttps=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
if(session_status()!==PHP_SESSION_ACTIVE){
    session_name('metroguide_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if($provided!==''){
    if(!hash_equals($expected,$provided))send_not_found();
    session_regenerate_id(true);
    $_SESSION['_admin_invite_gate_until']=time()+600;
    header('Location: /admin/hidden-portal-auth/',true,303);
    exit;
}
$gateUntil=(int)($_SESSION['_admin_invite_gate_until']??0);
if($gateUntil<time())send_not_found();
require_once $projectRoot.'/app/bootstrap.php';
if(is_admin()) redirect(app_path('admin/index.php'));
$adminCount=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$errors=[];$identity=trim((string)($_POST['identity']??''));$name=trim((string)($_POST['name']??''));$username=trim((string)($_POST['username']??''));$email=trim((string)($_POST['email']??''));
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(time()>(int)($_SESSION['_admin_invite_gate_until']??0)) send_not_found();
    if($adminCount===0){
        $pw=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
        if($name===''||text_len($name)>80)$errors[]='Enter a name up to 80 characters.';
        if(!valid_username($username))$errors[]='Choose a valid administrator username.';
        if(username_exists($username))$errors[]='That username is already in use.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
        if(!$errors){$emailCheck=db()->prepare('SELECT 1 FROM users WHERE email=:e COLLATE NOCASE LIMIT 1');$emailCheck->execute([':e'=>$email]);if($emailCheck->fetchColumn())$errors[]='That email address is already used by another account.';}
        if(strlen($pw)<12)$errors[]='Use a password with at least 12 characters.';
        if($pw!==$confirm)$errors[]='Passwords do not match.';
        if(!$errors){
            db()->prepare("INSERT INTO users(name,username,email,password_hash,role) VALUES(:n,:u,:e,:p,'admin')")->execute([':n'=>$name,':u'=>$username,':e'=>$email,':p'=>password_hash($pw,PASSWORD_DEFAULT)]);
            $adminId=(int)db()->lastInsertId();
            session_regenerate_id(true);$_SESSION['user_id']=$adminId;$_SESSION['admin_id']=$adminId;$_SESSION['admin_name']=$name;$_SESSION['_admin_portal_authenticated']=true;audit_event('admin.first_account.created','critical',['admin_id'=>$adminId]);unset($_SESSION['_admin_invite_gate_until']);
            redirect(app_path('admin/index.php'));
        }
    }else{
        $attempts=(int)($_SESSION['_admin_login_attempts']??0);$last=(int)($_SESSION['_admin_login_last']??0);
        if($attempts>=8&&time()-$last<900)$errors[]='Too many attempts. Wait 15 minutes and try again.';
        else{
            $st=db()->prepare("SELECT id,name,password_hash,two_factor_enabled,two_factor_secret_enc FROM users WHERE (email=:identity COLLATE NOCASE OR username=:identity COLLATE NOCASE) AND role='admin' AND is_active=1 LIMIT 1");
            $st->execute([':identity'=>$identity]);$user=$st->fetch();
            if($user&&password_verify((string)($_POST['password']??''),$user['password_hash'])){
                $factorOk=(int)($user['two_factor_enabled']??0)!==1;
                if(!$factorOk){$secret=decrypt_private_value((string)$user['two_factor_secret_enc']);$factorOk=$secret!==null&&verify_totp_code($secret,trim((string)($_POST['totp_code']??'')));}
                if(!$factorOk){$_SESSION['_admin_login_attempts']=$attempts+1;$_SESSION['_admin_login_last']=time();audit_event('admin.login.second_factor_failed','critical');$errors[]='A valid six-digit authenticator code is required.';}else{
                session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['admin_id']=(int)$user['id'];$_SESSION['admin_name']=$user['name'];$_SESSION['_admin_portal_authenticated']=true;audit_event('admin.login.succeeded','info');unset($_SESSION['_admin_invite_gate_until'],$_SESSION['_admin_login_attempts'],$_SESSION['_admin_login_last']);
                redirect(app_path('admin/index.php'));
                }
            }
            if(!$errors){$_SESSION['_admin_login_attempts']=$attempts+1;$_SESSION['_admin_login_last']=time();audit_event('admin.login.failed','critical');$errors[]='Administrator username/email or password is incorrect.';}
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Restricted access</title><link rel="stylesheet" href="<?= app_path('assets/css/style.css') ?>"></head><body class="auth-body"><main class="auth-card"><span class="eyebrow">RESTRICTED ACCESS</span><h1><?= $adminCount===0?'Create the first administrator':'Administrator sign in' ?></h1><p>This private access page is available only through a valid administrator invitation.</p><?php foreach($errors as $error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endforeach; ?><form method="post" class="studio-fields"><?= csrf_field() ?><?php if($adminCount===0): ?><label>Full name<input name="name" required maxlength="80" value="<?= h($name) ?>"></label><label>Administrator username<input name="username" required minlength="3" maxlength="30" value="<?= h($username) ?>"></label><label>Email address<input type="email" name="email" required value="<?= h($email) ?>"></label><label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label><?php else: ?><label>Administrator username or email<input name="identity" required autocomplete="username" value="<?= h($identity) ?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><label>Authenticator code <span class="auth-field-hint">Required only if enabled</span><input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></label><?php endif; ?><button class="button button-primary button-block"><?= $adminCount===0?'Create administrator':'Sign in securely' ?></button></form></main></body></html>

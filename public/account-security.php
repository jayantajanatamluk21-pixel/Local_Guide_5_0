<?php
require_once dirname(__DIR__).'/app/bootstrap.php';require_login();$uid=current_user_id();$errors=[];$notice=get_flash();
$userStmt=db()->prepare('SELECT email,two_factor_enabled,two_factor_secret_enc FROM users WHERE id=:u');$userStmt->execute([':u'=>$uid]);$security=$userStmt->fetch();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=(string)($_POST['action']??'');
 if($action==='begin'){
  if(app_encryption_key()===null||!extension_loaded('openssl'))$errors[]='Set APP_ENCRYPTION_KEY in .env and enable PHP OpenSSL before setting up two-factor authentication.';
  else{$_SESSION['totp_setup_secret']=new_totp_secret();$_SESSION['totp_setup_user_id']=$uid;flash('success','Authenticator setup started. Add the displayed secret to an authenticator app, then verify a current code.');redirect(app_path('account-security.php'));}
 }elseif($action==='confirm'){
  $secret=(string)($_SESSION['totp_setup_secret']??'');$setupUser=(int)($_SESSION['totp_setup_user_id']??0);$code=trim((string)($_POST['totp_code']??''));
  if($secret===''||$setupUser!==$uid||!verify_totp_code($secret,$code))$errors[]='The authenticator code is invalid or the setup has expired.';
  else{db()->prepare('UPDATE users SET two_factor_secret_enc=:s,two_factor_enabled=1 WHERE id=:u')->execute([':s'=>encrypt_private_value($secret),':u'=>$uid]);unset($_SESSION['totp_setup_secret'],$_SESSION['totp_setup_user_id']);audit_event('account.two_factor.enabled','info');flash('success','Two-factor authentication is enabled for your account.');redirect(app_path('account-security.php'));}
 }elseif($action==='disable'){
  $secret=decrypt_private_value((string)$security['two_factor_secret_enc']);$code=trim((string)($_POST['totp_code']??''));
  if(!$secret||!verify_totp_code($secret,$code))$errors[]='Enter a valid authenticator code to disable two-factor authentication.';
  else{db()->prepare("UPDATE users SET two_factor_secret_enc='',two_factor_enabled=0 WHERE id=:u")->execute([':u'=>$uid]);audit_event('account.two_factor.disabled','warning');flash('success','Two-factor authentication has been disabled.');redirect(app_path('account-security.php'));}
 }
 $userStmt->execute([':u'=>$uid]);$security=$userStmt->fetch();
}
$setupSecret=((int)($_SESSION['totp_setup_user_id']??0)===$uid)?(string)($_SESSION['totp_setup_secret']??''):'';$pageTitle='Account security | '.setting('site_name','LocalGuide');$pageDescription='Manage sign-in security for your LocalGuide account.';include __DIR__.'/includes/header.php';
?>
<main id="main" class="container lg3-page"><div class="page-intro"><span class="eyebrow">ACCOUNT PROTECTION</span><h1>Security settings.</h1><p>Use a time-based one-time password (TOTP) authenticator as a second sign-in factor.</p></div>
<?php if($notice): ?><div class="alert alert-<?= h($notice['type']) ?>"><?= h($notice['message']) ?></div><?php endif; ?><?php if($errors): ?><div class="alert alert-error"><?php foreach($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?></div><?php endif; ?>
<section class="content-panel lg5-security-panel"><div class="panel-heading"><div><span class="eyebrow">TWO-FACTOR AUTHENTICATION</span><h2><?= (int)$security['two_factor_enabled']?'Enabled':'Not enabled' ?></h2><p>Authenticator apps such as Google Authenticator, Microsoft Authenticator or 1Password can generate the six-digit code.</p></div><span class="status-pill <?= (int)$security['two_factor_enabled']?'status-approved':'status-pending' ?>"><?= (int)$security['two_factor_enabled']?'Protected':'Optional' ?></span></div>
<?php if((int)$security['two_factor_enabled']): ?><form method="post" class="studio-fields"><?= csrf_field() ?><input type="hidden" name="action" value="disable"><label>Current authenticator code<input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label><button class="button button-outline">Disable two-factor authentication</button></form>
<?php elseif($setupSecret!==''): ?><div class="owner-callout"><strong>Save this secret in your authenticator app.</strong><p>Account: <?= h($security['email']) ?></p><code class="lg5-secret-code"><?= h($setupSecret) ?></code><p>LocalGuide never sends this secret to an external service. Store it in a secure authenticator and do not share it.</p></div><form method="post" class="studio-fields"><?= csrf_field() ?><input type="hidden" name="action" value="confirm"><label>Six-digit verification code<input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label><button class="button button-primary">Verify and enable</button></form>
<?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="begin"><button class="button button-primary">Set up authenticator</button></form><?php endif; ?></section>
<p class="form-footnote">Store a secure backup of your authenticator recovery method. If you lose the device, administrator-assisted recovery is not currently available from this page.</p></main>
<?php include __DIR__.'/includes/footer.php'; ?>

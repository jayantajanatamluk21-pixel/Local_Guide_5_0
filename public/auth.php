<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$mode=(string)($_POST['auth_mode']??$_GET['mode']??'login');
if(!in_array($mode,['login','register'],true))$mode='login';
$role=(string)($_POST['role']??$_GET['role']??'member');
if(!in_array($role,['member','owner'],true))$role='member';
$requestedNext=(string)($_POST['next']??$_GET['next']??($_SESSION['auth_intent']??''));
$next=safe_return_to($requestedNext,'account.php');
if(current_user()) redirect($next);
$errors=[];
$name=trim((string)($_POST['name']??''));
$username=trim((string)($_POST['username']??''));
$email=trim((string)($_POST['email']??''));
$identity=trim((string)($_POST['identity']??''));
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $pw=(string)($_POST['password']??'');
    if($mode==='register'){
        if($name===''||text_len($name)>80)$errors[]='Enter your name (up to 80 characters).';
        if($username===''){
            $errors[]='Please enter a username.';
        } elseif(text_len($username)<3){
            $errors[]='Username must be at least 3 characters long.';
        } elseif(text_len($username)>30){
            $errors[]='Username cannot exceed 30 characters.';
        } elseif(str_contains($username,' ')){
            $errors[]='Usernames cannot contain spaces. Use dots, underscores, or hyphens (e.g. '.h(preg_replace('/\s+/','_',strtolower($username))).').';
        } elseif(in_array(strtolower($username),['admin','administrator','root','support','help','system','owner'],true)){
            $errors[]='The username "'.h($username).'" is reserved for system use. Please choose another username.';
        } elseif(!valid_username($username)){
            $errors[]='Username must start with a letter or number, and can only contain letters, numbers, dots, underscores, or hyphens.';
        }
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
        if(strlen($pw)<12)$errors[]='Use a password with at least 12 characters.';
        if(!$errors){$st=db()->prepare('SELECT id FROM users WHERE email=:e COLLATE NOCASE');$st->execute([':e'=>$email]);if($st->fetchColumn())$errors[]='An account with this email already exists.';}
        if(!$errors&&username_exists($username))$errors[]='That username is already taken. Choose another.';
        if(!$errors){
            $st=db()->prepare("INSERT INTO users(name,username,email,password_hash,role) VALUES(:n,:u,:e,:p,:r)");
            $st->execute([':n'=>$name,':u'=>$username,':e'=>$email,':p'=>password_hash($pw,PASSWORD_DEFAULT),':r'=>$role]);
            $id=(int)db()->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id']=$id;
            $_SESSION['language']='en';
            audit_event('member.registered','info',['role'=>$role]);
            unset($_SESSION['auth_intent']);
            flash('success',$role==='owner'?'Welcome! Your owner workspace is ready.':'Your account has been created.');
            redirect($next !== app_path('account.php') ? $next : ($role==='owner'?app_path('owner/index.php'):app_path('account.php')));
        }
    }else{
        $attempts=(int)($_SESSION['member_login_attempts']??0);$last=(int)($_SESSION['member_login_last']??0);
        if($attempts>=8&&time()-$last<900)$errors[]='Too many sign-in attempts. Please wait 15 minutes.';
        else{
            $st=db()->prepare("SELECT id,name,password_hash,role,preferred_language,two_factor_enabled,two_factor_secret_enc FROM users WHERE (email=:identity COLLATE NOCASE OR username=:identity COLLATE NOCASE) AND role IN ('member','owner') AND is_active=1 LIMIT 1");
            $st->execute([':identity'=>$identity]);$u=$st->fetch();
            if($u&&password_verify($pw,$u['password_hash'])){
                $secondFactorOk=true;
                if((int)($u['two_factor_enabled']??0)===1){$secret=decrypt_private_value((string)$u['two_factor_secret_enc']);$secondFactorOk=$secret!==null&&verify_totp_code($secret,trim((string)($_POST['totp_code']??'')));}
                if(!$secondFactorOk){$_SESSION['member_login_attempts']=$attempts+1;$_SESSION['member_login_last']=time();audit_event('member.login.second_factor_failed','warning');$errors[]='A valid six-digit authenticator code is required for this account.';}
                else {
                session_regenerate_id(true);
                $_SESSION['user_id']=(int)$u['id'];
                $_SESSION['language']=$u['preferred_language']?:'en';
                unset($_SESSION['member_login_attempts'],$_SESSION['member_login_last'],$_SESSION['auth_intent']);
                audit_event('member.login.succeeded','info',['role'=>$u['role']]);
                flash('success','Welcome back, '.$u['name'].'.');
                redirect($next !== app_path('account.php') ? $next : ($u['role']==='owner'?app_path('owner/index.php'):app_path('account.php')));
                }
            }
            if(!$errors){$_SESSION['member_login_attempts']=$attempts+1;$_SESSION['member_login_last']=time();
            audit_event('member.login.failed','warning');
            $errors[]='Username/email or password is incorrect.';} 
        }
    }
}
$pageTitle=($mode==='register'?'Create account':'Sign in').' | '.setting('site_name','LocalGuide');
$pageDescription='Sign in or create a Local Guide account to save places, plan trips and connect with local businesses.';
$pageStylesheets=['assets/css/auth-smoke.css'];
$pageScripts=['assets/js/auth-smoke.js'];
$isAuthPage=true;
$notice=get_flash();
include __DIR__.'/includes/header.php';
?>
<main id="main" class="auth-v4-page">
  <div class="auth-v4-shell">
    <section class="auth-v4-story" aria-label="LocalGuide introduction">
      <div class="auth-story-orbit auth-story-orbit-one" aria-hidden="true"></div>
      <div class="auth-story-orbit auth-story-orbit-two" aria-hidden="true"></div>
      <a class="auth-story-brand" href="<?= app_path('index.php') ?>" aria-label="LocalGuide home">
        <span class="auth-story-mark"><i class="fa-solid fa-compass"></i></span>
        <span><strong><?= h(setting('site_name','LocalGuide')) ?></strong><small>DISCOVER MORE. EXPERIENCE BETTER.</small></span>
      </a>
      <div class="auth-story-copy">
        <span class="auth-story-kicker"><i class="fa-solid fa-sparkles"></i> YOUR CITY, CONNECTED</span>
        <h1>Make room for your <em>next discovery.</em></h1>
        <p>Find neighbourhood favourites, keep the places you love close, and make every local experience more memorable.</p>
        <div class="auth-story-points">
          <span><i class="fa-solid fa-heart"></i> Save places worth returning to</span>
          <span><i class="fa-solid fa-map-location-dot"></i> Unlock maps and directions</span>
          <span><i class="fa-solid fa-store"></i> Connect with local businesses</span>
        </div>
      </div>
      <div class="auth-story-showcase" aria-hidden="true">
        <div class="auth-showcase-map">
          <span class="auth-map-road auth-map-road-a"></span><span class="auth-map-road auth-map-road-b"></span><span class="auth-map-road auth-map-road-c"></span>
          <span class="auth-map-pin auth-map-pin-a"><i class="fa-solid fa-utensils"></i></span>
          <span class="auth-map-pin auth-map-pin-b"><i class="fa-solid fa-mug-hot"></i></span>
          <span class="auth-map-pin auth-map-pin-c"><i class="fa-solid fa-leaf"></i></span>
          <span class="auth-map-pulse"></span>
        </div>
        <div class="auth-showcase-note"><span class="auth-note-icon"><i class="fa-solid fa-location-dot"></i></span><span><strong>Your next favourite is closer.</strong><small>Discover something local today.</small></span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
      </div>
      <div class="auth-story-foot"><span>LOCAL KNOWLEDGE, THOUGHTFULLY CONNECTED</span><span><i class="fa-solid fa-circle"></i> EXPLORE YOUR WAY</span></div>
    </section>

    <section class="auth-v4-workspace" aria-label="Account access">
      <div class="auth-mobile-brand"><span class="auth-story-mark"><i class="fa-solid fa-compass"></i></span><strong><?= h(setting('site_name','LocalGuide')) ?></strong></div>
      <div class="auth-workspace-top"><span class="auth-step-label">YOUR LOCAL GUIDE ACCOUNT</span><a href="<?= app_path('index.php') ?>" class="auth-explore-link"><i class="fa-solid fa-arrow-left"></i> Back to explore</a></div>

      <div class="auth-mode-switch" role="tablist" aria-label="Choose account action" data-active-mode="<?= h($mode) ?>">
        <button type="button" class="auth-mode-tab <?= $mode==='login'?'is-active':'' ?>" id="auth-tab-login" role="tab" aria-controls="auth-panel-login" aria-selected="<?= $mode==='login'?'true':'false' ?>" data-auth-mode="login">Sign In</button>
        <button type="button" class="auth-mode-tab <?= $mode==='register'?'is-active':'' ?>" id="auth-tab-register" role="tab" aria-controls="auth-panel-register" aria-selected="<?= $mode==='register'?'true':'false' ?>" data-auth-mode="register">Create an Account</button>
        <span class="auth-mode-indicator" aria-hidden="true"></span>
      </div>

      <div class="auth-form-stage" id="auth-form-stage" data-active-mode="<?= h($mode) ?>" aria-busy="false">
        <div class="auth-vapor" aria-hidden="true"><span class="auth-vapor-puff auth-vapor-puff-one"></span><span class="auth-vapor-puff auth-vapor-puff-two"></span><span class="auth-vapor-puff auth-vapor-puff-three"></span></div>

        <section class="auth-panel <?= $mode==='login'?'is-active':'' ?>" id="auth-panel-login" role="tabpanel" aria-labelledby="auth-tab-login" <?= $mode==='login'?'':'hidden' ?>>
          <span class="auth-panel-eyebrow"><i class="fa-solid fa-door-open"></i> MEMBER ACCESS</span>
          <h2>Welcome <em>back.</em></h2>
          <p class="auth-panel-intro">Sign in to pick up right where your local discoveries left off.</p>
          <?php if($mode==='login'&&$notice): ?><div class="alert alert-<?= h($notice['type']) ?>" role="status"><?= h($notice['message']) ?></div><?php endif; ?>
          <?php if($mode==='login'&&$errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          <form method="post" action="<?= app_path('auth.php') ?>" class="studio-fields auth-v4-form" data-auth-form="login">
            <?= csrf_field() ?><input type="hidden" name="auth_mode" value="login"><input type="hidden" name="next" value="<?= h($next) ?>">
            <label>Username or email<input name="identity" type="text" required autocomplete="username" value="<?= h($mode==='login'?$identity:'') ?>" placeholder="you@example.com or username"></label>
            <label>Password<span class="auth-password-wrap"><input name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"><button type="button" class="auth-password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></span></label>
            <label>Authenticator code <span class="auth-field-hint">Required only when two-factor authentication is enabled</span><input name="totp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="6-digit code"></label>
            <button class="button button-primary button-block auth-submit" type="submit">Sign in <i class="fa-solid fa-arrow-right"></i></button>
          </form>
          <p class="auth-panel-switch">New to LocalGuide? <button type="button" data-auth-mode="register">Create an account <i class="fa-solid fa-arrow-right"></i></button></p>
        </section>

        <section class="auth-panel <?= $mode==='register'?'is-active':'' ?>" id="auth-panel-register" role="tabpanel" aria-labelledby="auth-tab-register" <?= $mode==='register'?'':'hidden' ?>>
          <span class="auth-panel-eyebrow"><i class="fa-solid fa-sparkles"></i> JOIN THE COMMUNITY</span>
          <h2>Start exploring <em>more.</em></h2>
          <p class="auth-panel-intro">Create one account for saved places, local experiences and business tools.</p>
          <?php if($mode==='register'&&$notice): ?><div class="alert alert-<?= h($notice['type']) ?>" role="status"><?= h($notice['message']) ?></div><?php endif; ?>
          <?php if($mode==='register'&&$errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          <form method="post" action="<?= app_path('auth.php') ?>" class="studio-fields auth-v4-form" data-auth-form="register">
            <?= csrf_field() ?><input type="hidden" name="auth_mode" value="register"><input type="hidden" name="next" value="<?= h($next) ?>">
            <label>Your name<input name="name" required maxlength="80" autocomplete="name" value="<?= h($mode==='register'?$name:'') ?>" placeholder="Your full name"></label>
            <label>Username<input name="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,29}" autocomplete="username" value="<?= h($mode==='register'?$username:'') ?>" placeholder="Choose a username"><small>3–30 characters; letters, numbers, dot, underscore or hyphen.</small></label>
            <label>Email address<input type="email" name="email" required autocomplete="email" value="<?= h($mode==='register'?$email:'') ?>" placeholder="you@example.com"></label>
            <label>Account type<select name="role"><option value="member" <?= $role==='member'?'selected':'' ?>>Local explorer</option><option value="owner" <?= $role==='owner'?'selected':'' ?>>Business owner</option></select><small>Business ownership is enabled after an administrator approves your claim.</small></label>
            <label>Password<span class="auth-password-wrap"><input name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="At least 12 characters"><button type="button" class="auth-password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></span></label>
            <button class="button button-primary button-block auth-submit" type="submit">Create my account <i class="fa-solid fa-arrow-right"></i></button>
          </form>
          <p class="auth-panel-switch">Already have an account? <button type="button" data-auth-mode="login">Sign in <i class="fa-solid fa-arrow-right"></i></button></p>
        </section>
      </div>
      <noscript><p class="auth-no-script">JavaScript is disabled. Use these links to switch forms: <a href="<?= h(auth_url($next,'login')) ?>">Sign in</a> · <a href="<?= h(auth_url($next,'register',$role)) ?>">Create an account</a>.</p></noscript>
      <p class="auth-privacy-note"><i class="fa-solid fa-shield-halved"></i> Your account details are protected. We never display your password.</p>
    </section>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>

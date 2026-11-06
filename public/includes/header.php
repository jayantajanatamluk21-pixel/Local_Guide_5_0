<?php
$pageTitle=$pageTitle??setting('site_name','LocalGuide');
$pageDescription=$pageDescription??setting('site_description','Discover local places and businesses.');
$canonicalUrl=$canonicalUrl??app_url(ltrim($_SERVER['REQUEST_URI']??'/','/'));
$ogImage=$ogImage??app_url('assets/img/og-default.svg');
$showDemoNotice=(int)db()->query("SELECT COUNT(*) FROM listings WHERE is_demo=1 AND status='published'")->fetchColumn()>0;
$member=current_user();
$unread=$member?unread_notification_count((int)$member['id']):0;
$activeNav=$activeNav??'';
?>
<!doctype html>
<html lang="<?= h($_SESSION['language']??'en') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pageTitle) ?></title><meta name="description" content="<?= h($pageDescription) ?>">
<link rel="canonical" href="<?= h($canonicalUrl) ?>"><meta name="theme-color" content="#10251c">
<meta property="og:type" content="website"><meta property="og:title" content="<?= h($pageTitle) ?>"><meta property="og:description" content="<?= h($pageDescription) ?>"><meta property="og:url" content="<?= h($canonicalUrl) ?>"><meta property="og:image" content="<?= h($ogImage) ?>"><meta name="twitter:card" content="summary_large_image">
<link rel="manifest" href="<?= app_path('manifest.webmanifest') ?>"><link rel="icon" href="<?= app_path('assets/icons/icon.svg') ?>" type="image/svg+xml"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= app_path('assets/css/style.css') ?>"><link rel="stylesheet" href="<?= app_path('assets/css/upgrade.css') ?>"><link rel="stylesheet" href="<?= app_path('assets/css/v4.css') ?>"><link rel="stylesheet" href="<?= app_path('assets/css/v5.css') ?>"><?php foreach(($pageStylesheets??[]) as $sheet): ?><link rel="stylesheet" href="<?= app_path((string)$sheet) ?>"><?php endforeach; ?>
<?php if(!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script><?php endif; ?>
</head>
<body class="lg4-body<?= !empty($isAuthPage)?' auth-route-body':'' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header lg4-header"><div class="container header-inner lg4-header-inner">
  <a class="brand lg4-brand" href="<?= app_path('index.php') ?>" aria-label="LocalGuide home"><span class="brand-mark"><i class="fa-solid fa-compass"></i></span><span class="brand-copy"><strong><?= h(setting('site_name','LocalGuide')) ?></strong><small>DISCOVER MORE. EXPERIENCE BETTER.</small></span></a>
  <button class="mobile-nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
  <nav class="main-nav lg4-main-nav" aria-label="Main navigation">
    <a class="<?= in_array($activeNav,['home','discover'],true)?'active':'' ?>" href="<?= app_path('index.php') ?>">Explore</a>
    <a class="<?= $activeNav==='ai'?'active':'' ?>" href="<?= app_path('guide-ai.php') ?>">Guide AI</a>
    <a class="<?= $activeNav==='map'?'active':'' ?>" href="<?= app_path('map.php') ?>">Map</a>
    <a class="<?= $activeNav==='marketplace'?'active':'' ?>" href="<?= app_path('marketplace.php') ?>">Services</a>
    <a class="<?= $activeNav==='events'?'active':'' ?>" href="<?= app_path('events.php') ?>">Events</a>
    <a class="<?= $activeNav==='community'?'active':'' ?>" href="<?= app_path('community.php') ?>">Community</a>
  </nav>
  <div class="header-actions lg4-header-actions">
    <?php if($member): ?>
      <a class="header-icon-link" href="<?= app_path('notifications.php') ?>" aria-label="Notifications"><i class="fa-regular fa-bell"></i><?php if($unread): ?><span class="notification-dot"><?= $unread>9?'9+':$unread ?></span><?php endif; ?></a>
      <a class="header-account-link" href="<?= app_path('account.php') ?>"><i class="fa-regular fa-user"></i> <?= h(t('My account')) ?></a><a class="header-icon-link" href="<?= app_path('account-security.php') ?>" aria-label="Account security" title="Account security"><i class="fa-solid fa-shield-halved"></i></a>
    <?php else: ?>
      <a class="header-account-link" href="<?= app_path('auth.php?mode=login') ?>">Sign in</a>
    <?php endif; ?>
    <button class="lg4-theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark theme" aria-pressed="false" title="Toggle colour theme"><i class="fa-regular fa-moon"></i></button>
    <form method="post" action="<?= app_path('language.php') ?>" class="language-switcher"><?= csrf_field() ?><input type="hidden" name="return_to" value="<?= h(ltrim($_SERVER['REQUEST_URI']??'index.php','/')) ?>"><label class="sr-only" for="language-select">Language</label><select id="language-select" name="language" onchange="this.form.submit()"><option value="en" <?= ($_SESSION['language']??'en')==='en'?'selected':'' ?>>EN</option><option value="hi" <?= ($_SESSION['language']??'en')==='hi'?'selected':'' ?>>हिं</option><option value="bn" <?= ($_SESSION['language']??'en')==='bn'?'selected':'' ?>>বাং</option></select></form>
    <a class="button button-dark button-small lg4-list-business" href="<?= app_path('submit-listing.php') ?>"><i class="fa-solid fa-plus"></i> List your business</a>
  </div>
</div></header>
<?php if($showDemoNotice): ?><div class="demo-notice lg4-demo-notice"><div class="container"><i class="fa-solid fa-circle-info"></i> Demo listings are included for preview. Replace them with verified local information before launch.</div></div><?php endif; ?>
<nav class="lg4-mobile-dock" aria-label="Quick navigation"><a href="<?= app_path('index.php') ?>"><i class="fa-solid fa-compass"></i><span>Explore</span></a><a href="<?= app_path('map.php') ?>"><i class="fa-solid fa-map"></i><span>Map</span></a><a href="<?= app_path('guide-ai.php') ?>"><i class="fa-solid fa-sparkles"></i><span>Guide AI</span></a><a href="<?= app_path('account.php') ?>"><i class="fa-regular fa-user"></i><span>Account</span></a></nav>

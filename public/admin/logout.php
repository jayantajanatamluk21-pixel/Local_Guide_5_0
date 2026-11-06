<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST') send_not_found();
verify_csrf();
$_SESSION=[];
if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
session_destroy();
redirect(app_path('index.php'));

<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST') redirect(app_path('index.php'));
verify_csrf();unset($_SESSION['user_id'],$_SESSION['admin_id'],$_SESSION['admin_name']);session_regenerate_id(true);flash('success','You have been signed out.');redirect(app_path('index.php'));

<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__));
$envFile = APP_ROOT . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) putenv($key . '=' . $value);
    }
}
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('metroguide_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
require_once APP_ROOT . '/app/functions.php';
require_once APP_ROOT . '/app/database.php';
require_once APP_ROOT . '/app/ai.php';
require_once APP_ROOT . '/app/qr.php';
require_once APP_ROOT . '/app/email.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(self), geolocation=(self), payment=()');
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
try { db(); } catch (Throwable $e) {
    http_response_code(500); error_log('[MetroGuide] ' . $e->getMessage());
    echo '<!doctype html><meta charset="utf-8"><title>MetroGuide setup</title><main style="max-width:700px;margin:10vh auto;font:16px system-ui;padding:24px"><h1>MetroGuide could not start</h1><p>Enable PHP PDO SQLite and ensure the <code>storage</code> folder is writable.</p>';
    if (filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN)) echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</main>'; exit;
}

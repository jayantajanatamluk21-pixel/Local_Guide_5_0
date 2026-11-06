<?php
http_response_code(404);
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Page not found</title><body style="margin:0;background:#f5f7f5;color:#20332d;font:16px system-ui,sans-serif"><main style="max-width:640px;margin:12vh auto;padding:32px"><p style="font-weight:800;letter-spacing:.12em;color:#718078">404 · NOT FOUND</p><h1>That page could not be found.</h1><p>The address may be incorrect or the page may no longer be available.</p><a href="/index.php">Return to explore</a></main></body></html>';
exit;

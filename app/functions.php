<?php
declare(strict_types=1);
function text_len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (preg_match_all('/./us', $s, $m) ?: strlen($s)); }
function text_substr(string $s, int $start, ?int $length=null): string {
    if (function_exists('mb_substr')) return mb_substr($s, $start, $length, 'UTF-8');
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($s);
    return implode('', array_slice($chars, $start, $length));
}
function text_upper(string $s): string { return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s); }
function text_lower(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
function text_excerpt(string $s, int $width, string $suffix='…'): string { return text_len($s) <= $width ? $s : rtrim(text_substr($s, 0, max(0, $width - text_len($suffix)))) . $suffix; }
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_path(string $p=''): string { return '/' . ltrim($p, '/'); }
function app_url(string $p=''): string {
    $base = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    if ($base !== '') return $base . ($p !== '' ? '/' . ltrim($p, '/') : '');
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000') . ($p !== '' ? '/' . ltrim($p, '/') : '');
}
function redirect(string $to): never { header('Location: '.$to, true, 303); exit; }
function flash(string $type,string $msg): void { $_SESSION['_flash']=['type'=>$type,'message'=>$msg]; }
function get_flash(): ?array { $v=$_SESSION['_flash']??null; unset($_SESSION['_flash']); return is_array($v)?$v:null; }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return (string)$_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.h(csrf_token()).'">'; }
function verify_csrf(): void { if(!hash_equals(csrf_token(),(string)($_POST['csrf_token']??''))){http_response_code(419);exit('Form expired. Refresh and try again.');} }
function is_admin(): bool {
    if (empty($_SESSION['_admin_portal_authenticated']) || empty($_SESSION['admin_id'])) return false;
    $id = (int)$_SESSION['admin_id'];
    if ($id <= 0 || (int)($_SESSION['user_id'] ?? 0) !== $id) return false;
    try {
        $st = db()->prepare("SELECT 1 FROM users WHERE id=:id AND role='admin' AND is_active=1 LIMIT 1");
        $st->execute([':id'=>$id]);
        if ($st->fetchColumn()) return true;
    } catch (Throwable $e) {
        error_log('[MetroGuide] Admin session check failed: '.$e->getMessage());
    }
    unset($_SESSION['_admin_portal_authenticated'], $_SESSION['admin_id'], $_SESSION['admin_name']);
    return false;
}
function valid_username(string $username): bool { return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,29}$/', $username) && !in_array(strtolower($username), ['admin','administrator','root','support','help','system','owner'], true); }
function username_exists(string $username, ?int $exceptId=null): bool { $sql='SELECT 1 FROM users WHERE username=:u COLLATE NOCASE'; $args=[':u'=>$username]; if($exceptId!==null){$sql.=' AND id<>:id';$args[':id']=$exceptId;} $sql.=' LIMIT 1'; $st=db()->prepare($sql);$st->execute($args);return (bool)$st->fetchColumn(); }
function send_not_found(): never {
    http_response_code(404);
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Page not found</title><body style="margin:0;background:#f5f7f5;color:#20332d;font:16px system-ui,sans-serif"><main style="max-width:640px;margin:12vh auto;padding:32px"><p style="font-weight:800;letter-spacing:.12em;color:#718078">404 · NOT FOUND</p><h1>That page could not be found.</h1><p>The address may be incorrect or the page may no longer be available.</p><a href="/index.php" style="display:inline-block;margin-top:12px;color:#146b60;font-weight:700">Return to explore</a></main></body></html>';
    exit;
}
function safe_return_to(string $candidate, string $fallback='account.php'): string {
    $candidate = trim($candidate);
    if ($candidate === '' || str_contains($candidate, "\0") || str_contains($candidate, "\\") || preg_match('/[\r\n\x00-\x1F]/', $candidate)) return app_path($fallback);
    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return app_path($fallback);
    $path = (string)($parts['path'] ?? '/');
    $decodedPath = rawurldecode($path);
    // Encoded slashes/backslashes can be normalized by proxies or web servers.
    // Validate the decoded form as well as the literal form before using a redirect.
    if ($path === '' || str_starts_with($path, '//') || str_starts_with($decodedPath, '//') || str_contains($decodedPath, '\\') || preg_match('#(^|/)\.\.(/|$)#', $decodedPath)) return app_path($fallback);
    if (!str_starts_with($path, '/')) $path = app_path($path);
    $guardPath = '/'.ltrim($decodedPath, '/');
    if (preg_match('#^/(?:admin(?:/|$)|setup\.php$|auth\.php$)#i', $guardPath)) return app_path($fallback);
    $query = isset($parts['query']) ? '?'.$parts['query'] : '';
    return $path.$query;
}
function current_request_target(): string {
    return safe_return_to((string)($_SERVER['REQUEST_URI'] ?? '/index.php'), 'index.php');
}
function auth_url(string $returnTo, string $mode='login', string $role='member'): string {
    $mode = in_array($mode, ['login','register'], true) ? $mode : 'login';
    $params = ['mode'=>$mode, 'next'=>safe_return_to($returnTo, 'account.php')];
    if ($mode === 'register' && in_array($role, ['member','owner'], true)) $params['role'] = $role;
    return app_path('auth.php?'.http_build_query($params));
}
function require_admin(): void {
    if (!is_admin()) send_not_found();
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

function slugify(string $s): string { $s=text_lower(trim($s)); $a=function_exists('iconv')?iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s):false; $s=$a!==false?$a:$s; return trim(preg_replace('/[^a-z0-9]+/','-',$s)??'','-')?:'local-place'; }
function unique_slug(string $name,?int $skip=null): string {
    $base=slugify($name); $slug=$base; $n=2;
    do { $sql='SELECT id FROM listings WHERE slug=:slug'; $p=[':slug'=>$slug]; if($skip){$sql.=' AND id<>:id';$p[':id']=$skip;} $st=db()->prepare($sql);$st->execute($p);if(!$st->fetchColumn())return $slug;$slug=$base.'-'.$n++; } while(true);
}
function setting(string $k,string $d=''): string { $s=db()->prepare('SELECT value FROM settings WHERE key=:k');$s->execute([':k'=>$k]);$v=$s->fetchColumn();return $v===false?$d:(string)$v; }
function save_setting(string $k,string $v): void { db()->prepare('INSERT INTO settings(key,value) VALUES(:k,:v) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([':k'=>$k,':v'=>$v]); }
function categories(): array { return ['Food & Cafes','Heritage & Spots','Shopping','Health & Wellness','Professional Services','Home Services','Tech Hubs','Stay & Travel','Other']; }
function listing_image(array $l): string {
    if(!empty($l['image_path'])) return app_path((string)$l['image_path']);
    if(!empty($l['image_url']) && preg_match('#^https?://#i',(string)$l['image_url'])) return (string)$l['image_url'];
    return app_path('assets/img/place-placeholder.svg');
}
function map_url(array $l, string $mode='driving'): string {
    $slug = trim((string)($l['slug'] ?? ''));
    $returnTo = $slug !== '' ? app_path('index.php?listing='.rawurlencode($slug)) : app_path('index.php');
    $mode = in_array($mode, ['driving','walking'], true) ? $mode : 'driving';
    return app_path('location-access.php?'.http_build_query(['id'=>(int)($l['id'] ?? 0),'action'=>'directions','mode'=>$mode,'return_to'=>$returnTo]));
}
function valid_http_url(string $v): string { $v=trim($v);if($v==='')return '';if(!preg_match('#^https?://#i',$v))$v='https://'.$v;return filter_var($v,FILTER_VALIDATE_URL)?$v:''; }
function upload_listing_image(array $f): ?string {
    if(($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(($f['error']??0)!==UPLOAD_ERR_OK)throw new RuntimeException('Image upload failed.');
    if((int)($f['size']??0)>5*1024*1024)throw new RuntimeException('Images must be 5 MB or smaller.');
    $tmp=(string)($f['tmp_name']??'');if(!is_uploaded_file($tmp))throw new RuntimeException('Upload could not be verified.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($allowed[$mime]))throw new RuntimeException('Use a JPG, PNG, WEBP or GIF image.');
    $dir=APP_ROOT.'/public/uploads';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Cannot create upload folder.');
    if(function_exists('imagewebp') && in_array($mime, ['image/jpeg','image/png'], true)){
        $src=$mime==='image/jpeg'?@imagecreatefromjpeg($tmp):@imagecreatefrompng($tmp);
        if($src){
            $name=bin2hex(random_bytes(16)).'.webp';
            if(@imagewebp($src,$dir.'/'.$name,82)){@imagedestroy($src);return 'uploads/'.$name;}
            @imagedestroy($src);
        }
    }
    $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(!move_uploaded_file($tmp,$dir.'/'.$name))throw new RuntimeException('Could not save the image.');
    return 'uploads/'.$name;
}
function delete_uploaded_image(?string $p): void { if($p&&str_starts_with($p,'uploads/')){@unlink(APP_ROOT.'/public/'.$p);} }
function rating_summary(int $id): array { $s=db()->prepare("SELECT COUNT(*) n,AVG(rating) a FROM reviews WHERE listing_id=:id AND status='approved'");$s->execute([':id'=>$id]);$r=$s->fetch();return ['count'=>(int)$r['n'],'average'=>$r['a']!==null?round((float)$r['a'],1):null]; }
function format_date(string $d): string { try{return (new DateTimeImmutable($d))->format('M j, Y');}catch(Throwable){return $d;} }
function current_admin_name(): string { return (string)($_SESSION['admin_name']??'Administrator'); }

/** Current signed-in public member or an administrator authenticated through the invite portal. */
function current_user(): ?array {
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) return null;
    $s = db()->prepare('SELECT id,name,username,email,role,phone,preferred_language,avatar_path,home_city,interests,profile_visibility FROM users WHERE id=:id AND is_active=1 LIMIT 1');
    $s->execute([':id'=>$id]);
    $u = $s->fetch();
    if (!$u) return null;
    if ($u['role'] === 'admin' && !is_admin()) return null;
    if (!in_array($u['role'], ['member','owner','admin'], true)) return null;
    return $u;
}
function current_user_id(): int { $u=current_user(); return $u ? (int)$u['id'] : 0; }
function is_owner(): bool { $u=current_user(); return $u && in_array($u['role'], ['owner','admin'], true); }
function require_login(?string $returnTo=null): void {
    if (current_user()) return;
    $target = safe_return_to($returnTo ?? current_request_target(), 'account.php');
    $_SESSION['auth_intent'] = $target;
    flash('warning','Sign in or create an account to view map locations and directions.');
    redirect(auth_url($target, 'login'));
}
function consume_auth_intent(string $fallback='account.php'): string {
    $target = (string)($_SESSION['auth_intent'] ?? '');
    unset($_SESSION['auth_intent']);
    return safe_return_to($target, $fallback);
}
function require_owner(): void { require_login(); if(!is_owner()){http_response_code(404);send_not_found();} }
function visitor_hash(): string {
    if (empty($_COOKIE['mg_visitor'])) {
        $token = bin2hex(random_bytes(16));
        setcookie('mg_visitor',$token,['expires'=>time()+31536000,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']);
        $_COOKIE['mg_visitor']=$token;
    }
    return hash('sha256',(string)$_COOKIE['mg_visitor']);
}
function record_search(string $query, string $area, int $count): void {
    if ($query==='' && $area==='') return;
    db()->prepare('INSERT INTO search_logs(query_text,area_text,result_count,visitor_hash) VALUES(:q,:a,:n,:v)')->execute([':q'=>text_substr($query,0,160),':a'=>text_substr($area,0,160),':n'=>$count,':v'=>visitor_hash()]);
}
function record_listing_view(int $listingId): void {
    $hash=visitor_hash();
    $s=db()->prepare("SELECT COUNT(*) FROM listing_views WHERE listing_id=:id AND visitor_hash=:v AND viewed_at >= datetime('now','-30 minutes')");
    $s->execute([':id'=>$listingId,':v'=>$hash]);
    if((int)$s->fetchColumn()===0) db()->prepare('INSERT INTO listing_views(listing_id,visitor_hash) VALUES(:id,:v)')->execute([':id'=>$listingId,':v'=>$hash]);
}
function is_favorite(int $listingId): bool {
    $uid=current_user_id(); if(!$uid)return false;
    $s=db()->prepare('SELECT 1 FROM favorites WHERE user_id=:u AND listing_id=:l');$s->execute([':u'=>$uid,':l'=>$listingId]);return (bool)$s->fetchColumn();
}
function notify_user(int $userId, string $title, string $body='', string $href=''): void {
    if ($userId <= 0) return;
    $safeHref = safe_return_to($href, 'account.php');
    db()->prepare('INSERT INTO user_notifications(user_id,title,body,href) VALUES(:u,:t,:b,:h)')
        ->execute([':u'=>$userId, ':t'=>text_substr($title,0,120), ':b'=>text_substr($body,0,500), ':h'=>$safeHref]);
}
function unread_notification_count(?int $userId=null): int {
    $userId = $userId ?? current_user_id();
    if ($userId <= 0) return 0;
    $s=db()->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id=:u AND read_at IS NULL');
    $s->execute([':u'=>$userId]); return (int)$s->fetchColumn();
}
function collection_share_url(array $collection): string {
    return app_url('collection-view.php?token='.rawurlencode((string)$collection['share_token']));
}
function make_share_token(): string { return bin2hex(random_bytes(24)); }
function json_response(array $payload, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store'); echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function api_rate_limit(string $route, int $limit=90, int $windowSeconds=60): void {
    $visitor=visitor_hash();
    $s=db()->prepare("SELECT COUNT(*) FROM api_request_logs WHERE route=:r AND visitor_hash=:v AND requested_at>=datetime('now',:window)");
    $s->execute([':r'=>$route, ':v'=>$visitor, ':window'=>'-'.$windowSeconds.' seconds']);
    if((int)$s->fetchColumn()>=$limit) json_response(['error'=>'rate_limited','message'=>'Please slow down and try again.'],429);
    db()->prepare('INSERT INTO api_request_logs(route,visitor_hash) VALUES(:r,:v)')->execute([':r'=>$route,':v'=>$visitor]);
}
function listing_public_payload(array $l, bool $precise=false): array {
    $payload=['id'=>(int)$l['id'],'name'=>(string)$l['name'],'slug'=>(string)$l['slug'],'category'=>(string)$l['category'],
        'location'=>(string)$l['location'],'city'=>(string)($l['city']??''),'description'=>(string)$l['description'],
        'image'=>listing_image($l),'verified'=>(bool)($l['verified']??false),'featured'=>(bool)($l['featured']??false)];
    if($precise){$payload['address']=(string)($l['address']??'');$payload['latitude']=$l['latitude']!==null?(float)$l['latitude']:null;$payload['longitude']=$l['longitude']!==null?(float)$l['longitude']:null;}
    return $payload;
}

function t(string $key): string {
    $lang=(string)($_SESSION['language']??(current_user()['preferred_language']??setting('default_language','en')));
    $dict=[
      'en'=>['Explore'=>'Explore','Events'=>'Events','Offers'=>'Offers','Map'=>'Map','Sign in'=>'Sign in','My account'=>'My account','List your business'=>'List your business','Explore by category'=>'Explore by category','Explore local places'=>'Explore local places','Search'=>'Search','Favourites'=>'Favourites','Trip planner'=>'Trip planner','Good work deserves to be found.'=>'Good work deserves to be found.'],
      'hi'=>['Explore'=>'खोजें','Events'=>'कार्यक्रम','Offers'=>'ऑफ़र','Map'=>'नक्शा','Sign in'=>'साइन इन','My account'=>'मेरा खाता','List your business'=>'अपना व्यवसाय जोड़ें','Explore by category'=>'श्रेणी के अनुसार खोजें','Explore local places'=>'स्थानीय जगहें देखें','Search'=>'खोजें','Favourites'=>'पसंदीदा','Trip planner'=>'यात्रा योजना','Good work deserves to be found.'=>'अच्छे व्यवसाय लोगों तक पहुँचने चाहिए।'],
      'bn'=>['Explore'=>'ঘুরে দেখুন','Events'=>'অনুষ্ঠান','Offers'=>'অফার','Map'=>'মানচিত্র','Sign in'=>'সাইন ইন','My account'=>'আমার অ্যাকাউন্ট','List your business'=>'ব্যবসা যোগ করুন','Explore by category'=>'বিভাগ অনুযায়ী দেখুন','Explore local places'=>'স্থানীয় জায়গা দেখুন','Search'=>'খুঁজুন','Favourites'=>'পছন্দের তালিকা','Trip planner'=>'ভ্রমণ পরিকল্পনা','Good work deserves to be found.'=>'ভালো ব্যবসার পরিচিতি ছড়িয়ে পড়ুক।']
    ];
    return $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
}
function local_return_url(string $candidate, string $fallback='index.php'): string {
    if ($candidate==='' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i',$candidate) || str_contains($candidate,"\r") || str_contains($candidate,"\n")) return app_path($fallback);
    return str_starts_with($candidate,'/') ? $candidate : app_path($candidate);
}


/** Store a privacy-minimised security/business event. Never pass passwords, tokens or secrets. */
function audit_event(string $event, string $severity='info', array $context=[]): void {
    try {
        $severity = in_array($severity, ['info','warning','critical'], true) ? $severity : 'info';
        $user = current_user();
        $visitor = (string)($_COOKIE['mg_visitor'] ?? '');
        $fingerprint = $visitor !== '' ? hash('sha256', $visitor . '|' . (getenv('APP_AUDIT_SALT') ?: 'localguide-audit')) : '';
        $clean = [];
        foreach ($context as $key=>$value) {
            if (preg_match('/password|secret|token|authorization|cookie|credential/i', (string)$key)) continue;
            if (is_scalar($value) || $value === null) $clean[(string)$key] = is_string($value) ? text_excerpt($value, 160) : $value;
        }
        db()->prepare('INSERT INTO security_audit_logs(actor_user_id,actor_role,event_key,severity,context_json,visitor_fingerprint) VALUES(:u,:r,:e,:s,:c,:v)')
            ->execute([':u'=>$user ? (int)$user['id'] : null, ':r'=>$user ? (string)$user['role'] : 'guest', ':e'=>text_excerpt($event,100), ':s'=>$severity, ':c'=>json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}', ':v'=>$fingerprint]);
    } catch (Throwable $e) { error_log('[LocalGuide audit] '.$e->getMessage()); }
}

/** Return configuration state only; never return the configured value itself. */
function integration_is_configured(string $key): bool {
    $value = getenv($key);
    return is_string($value) && trim($value) !== '' && !str_contains($value, 'replace_me');
}

function integration_catalog(): array {
    return [
        'generative_ai_gemini'=>['name'=>'Google Gemini AI','provider'=>'Google Generative AI','env'=>['GEMINI_API_KEY'],'use'=>'Live AI review summaries, smart copy generation, and assistant responses'],
        'generative_ai'=>['name'=>'Generative AI (OpenAI)','provider'=>'OpenAI-compatible API','env'=>['OPENAI_API_KEY'],'use'=>'AI answers, generated review summaries and multilingual writing'],
        'maps_routing'=>['name'=>'Maps / routing','provider'=>'Configured map or routing provider','env'=>['MAPS_PROVIDER_API_KEY'],'use'=>'Managed geocoding, route calculation and reliable ETA'],
        'payments_razorpay'=>['name'=>'Razorpay payments','provider'=>'Razorpay','env'=>['RAZORPAY_KEY_ID','RAZORPAY_KEY_SECRET'],'use'=>'Checkout, subscription billing and signed payment webhooks'],
        'payments_stripe'=>['name'=>'Stripe payments','provider'=>'Stripe','env'=>['STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET'],'use'=>'Checkout, subscription billing and signed webhooks'],
        'email_delivery'=>['name'=>'Transactional email','provider'=>'Resend-compatible email service','env'=>['RESEND_API_KEY'],'use'=>'Email verification, receipts and notifications'],
        'web_push'=>['name'=>'Web push','provider'=>'Web Push / VAPID','env'=>['VAPID_PUBLIC_KEY','VAPID_PRIVATE_KEY'],'use'=>'Browser push subscription delivery'],
        'captcha'=>['name'=>'Abuse protection','provider'=>'Turnstile-compatible CAPTCHA','env'=>['TURNSTILE_SECRET_KEY','TURNSTILE_SITE_KEY'],'use'=>'Signup and public form abuse protection'],
    ];
}


function app_encryption_key(): ?string {
    $raw = trim((string)(getenv('APP_ENCRYPTION_KEY') ?: ''));
    if ($raw === '') return null;
    $decoded = base64_decode($raw, true);
    return is_string($decoded) && strlen($decoded) === 32 ? $decoded : null;
}
function encrypt_private_value(string $plain): string {
    $key=app_encryption_key(); if($key===null || !function_exists('openssl_encrypt')) throw new RuntimeException('APP_ENCRYPTION_KEY and OpenSSL are required for encrypted account security settings.');
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false)throw new RuntimeException('Unable to encrypt account security data.');
    return base64_encode($iv.$tag.$cipher);
}
function decrypt_private_value(string $encoded): ?string {
    $key=app_encryption_key();if($key===null||!function_exists('openssl_decrypt'))return null;
    $raw=base64_decode($encoded,true);if(!is_string($raw)||strlen($raw)<29)return null;
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
    return is_string($plain)?$plain:null;
}
function totp_base32_encode(string $data): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($data) as $char)$bits.=str_pad(decbin(ord($char)),8,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,5) as $chunk)$out.=$alphabet[bindec(str_pad($chunk,5,'0'))];return $out;
}
function totp_base32_decode(string $value): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$value=strtoupper(preg_replace('/[^A-Z2-7]/','',$value)??'');$bits='';foreach(str_split($value) as $char){$pos=strpos($alphabet,$char);if($pos===false)continue;$bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);}$out='';foreach(str_split($bits,8) as $chunk){if(strlen($chunk)===8)$out.=chr(bindec($chunk));}return $out;
}
function totp_code(string $secret, ?int $timestamp=null): string {
    $counter=(int)floor(($timestamp??time())/30);$high=($counter>>32)&0xffffffff;$low=$counter&0xffffffff;$msg=pack('N2',$high,$low);$hash=hash_hmac('sha1',$msg,$secret,true);$offset=ord($hash[19])&0x0f;$binary=((ord($hash[$offset])&0x7f)<<24)|((ord($hash[$offset+1])&0xff)<<16)|((ord($hash[$offset+2])&0xff)<<8)|(ord($hash[$offset+3])&0xff);return str_pad((string)($binary%1000000),6,'0',STR_PAD_LEFT);
}
function verify_totp_code(string $secret, string $code, ?int $timestamp=null): bool {
    if(!preg_match('/^\d{6}$/',$code))return false;$now=$timestamp??time();for($offset=-1;$offset<=1;$offset++)if(hash_equals(totp_code($secret,$now+($offset*30)),$code))return true;return false;
}
function new_totp_secret(): string { return totp_base32_encode(random_bytes(20)); }

<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$returnTo=safe_return_to((string)($_GET['return_to']??''),'index.php');
require_login($returnTo);
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
$id=(int)($_GET['id']??0);
$action=(string)($_GET['action']??'directions');
$mode=(string)($_GET['mode']??'driving');
if(!in_array($action,['map','directions'],true)||!in_array($mode,['driving','walking'],true)||$id<=0) send_not_found();
$st=db()->prepare("SELECT id,slug,name,address,location,city,latitude,longitude FROM listings WHERE id=:id AND status='published' LIMIT 1");
$st->execute([':id'=>$id]);$listing=$st->fetch();
if(!$listing) send_not_found();
$destination='';
if($listing['latitude']!==null&&$listing['longitude']!==null){$destination=(string)$listing['latitude'].','.(string)$listing['longitude'];}
else{$destination=trim((string)$listing['address'])?:trim((string)$listing['location'].', '.(string)$listing['city']);}
if($destination==='') send_not_found();
if($action==='map'){$url='https://www.google.com/maps/search/?api=1&query='.rawurlencode($destination);}
else{$url='https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination).'&travelmode='.rawurlencode($mode);}
redirect($url);

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$envPath=$root.'/.env';$lines=is_file($envPath)?(file($envPath,FILE_IGNORE_NEW_LINES)?:[]):[];$token=bin2hex(random_bytes(32));$found=false;
foreach($lines as &$line){if(preg_match('/^\s*ADMIN_INVITE_TOKEN\s*=/',(string)$line)){$line='ADMIN_INVITE_TOKEN='.$token;$found=true;}}
unset($line);
if(!$found){$lines[]='';$lines[]='# Private administrator portal invitation token (rotate after sharing).';$lines[]='ADMIN_INVITE_TOKEN='.$token;}
$content=implode(PHP_EOL,$lines).PHP_EOL;
if(file_put_contents($envPath,$content,LOCK_EX)===false){fwrite(STDERR,"Could not write .env. Check project folder permissions.\n");exit(1);}
$base=rtrim((string)(getenv('APP_URL')?:'http://127.0.0.1:8000'),'/');
foreach($lines as $line){if(str_starts_with((string)$line,'APP_URL=')){ $configured=trim(substr((string)$line,8));if($configured!=='')$base=rtrim($configured,'/');}}
$url=$base.'/admin/hidden-portal-auth/?invite='.$token;
fwrite(STDOUT,"Administrator invitation generated.\n\nOpen this URL in your browser (do not share it publicly):\n".$url."\n\nThe invitation token was stored in .env and is not written to application source files.\nRotate it after use by running this tool again.\n");

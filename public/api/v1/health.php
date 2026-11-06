<?php
require_once dirname(__DIR__,3).'/app/bootstrap.php';
json_response(['status'=>'ok','app'=>'LocalGuide','version'=>setting('platform_version','4.0.0'),'api'=>'v1','time'=>gmdate('c')]);

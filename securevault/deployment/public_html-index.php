<?php
// Copy to public_html/index.php along with the contents of securevault/public.
// This example expects sibling directories: /home/account/securevault and /home/account/public_html.
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
$core=dirname(__DIR__).'/securevault';
require $core.'/bootstrap/first-run.php';
if(file_exists($maintenance=$core.'/storage/framework/maintenance.php')) require $maintenance;
require $core.'/vendor/autoload.php';
(require_once $core.'/bootstrap/app.php')->handleRequest(Request::capture());

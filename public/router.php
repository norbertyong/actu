<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$routes=['/'=>'index.php','/index.php'=>'index.php','/vulprod.php'=>'vulprod.php','/favorites.php'=>'favorites.php','/sources.php'=>'sources.php','/admin.php'=>'admin.php','/manage.php'=>'manage.php','/analytics.php'=>'analytics.php','/alerts.php'=>'alerts.php','/login.php'=>'login.php','/logout.php'=>'logout.php','/action.php'=>'action.php','/opml.php'=>'opml.php','/export.php'=>'export.php','/privacy.php'=>'privacy.php','/reset.php'=>'reset.php','/release.php'=>'release.php'];
if(isset($routes[$path])) { require __DIR__.'/'.$routes[$path];return true; }
if(in_array($path,['/assets/app.css','/assets/app.js'],true)) return false;
http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Page introuvable.';return true;

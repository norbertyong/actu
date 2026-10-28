<?php
require __DIR__.'/../app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405);exit('Utilisez le bouton Déconnexion.'); }
verify_csrf();$_SESSION=[];session_regenerate_id(true);session_destroy();redirect('/login.php');

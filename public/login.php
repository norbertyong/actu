<?php
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/view.php';
if(is_admin()) redirect('/admin.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf();if(login(input('user','',$_POST),(is_string($_POST['pass']??null)?$_POST['pass']:''))) redirect('/admin.php');$error='Connexion refusée. Vérifiez vos identifiants ou réessayez après 15 minutes si plusieurs tentatives ont échoué.'; }
page_start('Connexion');heading('ESPACE ADMINISTRATEUR','Pilotez votre veille.','Accès réservé à la gestion des sources, des alertes et des statistiques.');
?><form method="post" class="panel login-form"><?=csrf_field()?><?php if($error):?><p class="notice error" role="alert"><?=e($error)?></p><?php endif ?><label for="user">Utilisateur</label><input id="user" name="user" autocomplete="username" required maxlength="150"><label for="pass">Mot de passe</label><input id="pass" type="password" name="pass" autocomplete="current-password" required><button>Se connecter</button><p class="muted">Session limitée à 30 minutes d’inactivité. Les tentatives répétées sont temporairement bloquées.</p></form><?php page_end();

<?php
declare(strict_types=1);
// This endpoint exists only in a deployment package containing a private installation key.
$root=__DIR__.'/..';
$keyFile=$root.'/config/install.key';$configFile=$root.'/config/local.php';
if(!is_file($root.'/config/install.enabled') || is_file($configFile)) { http_response_code(404);exit('Installation indisponible.'); }
if(!is_file($keyFile)) {
    $handle=@fopen($keyFile,'x');
    if($handle){chmod($keyFile,0600);fwrite($handle,bin2hex(random_bytes(32)));fclose($handle);}
}
$base=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/install.php'));if($base==='/' || $base==='.')$base='';
$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
session_name('actu_setup');session_start(['cookie_httponly'=>true,'cookie_secure'=>$secure,'cookie_samesite'=>'Strict','cookie_path'=>$base.'/','use_strict_mode'=>true]);
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
function esc(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$checks=['PHP 8.2 ou supérieur'=>PHP_VERSION_ID>=80200];
foreach(['pdo_sqlite','curl','SimpleXML','mbstring','session'] as $ext)$checks['Extension '.$ext]=extension_loaded($ext);
$checks['Configuration accessible en écriture']=is_writable($root.'/config');
$checks['Stockage accessible en écriture']=is_dir($root.'/storage') && is_writable($root.'/storage');
$checks['Code privé généré']=is_file($keyFile) && strlen(trim(file_get_contents($keyFile)))===64;
$checks['Fonction de configuration disponible']=function_exists('putenv');
$csrf=$_SESSION['setup_csrf']??=bin2hex(random_bytes(32));$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if(!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf']))throw new RuntimeException('Formulaire expiré. Rechargez la page.');
  if(in_array(false,$checks,true))throw new RuntimeException('L’hébergement doit satisfaire les prérequis ci-dessous.');
  $key=is_string($_POST['key']??null)?trim($_POST['key']):'';
  if(strlen($key)!==64 || !hash_equals(trim(file_get_contents($keyFile)),$key))throw new RuntimeException('Code d’installation incorrect. Ouvrez .actu/config/install.key dans le gestionnaire de fichiers.');
  $user=is_string($_POST['user']??null)?trim($_POST['user']):'';$pass=is_string($_POST['pass']??null)?$_POST['pass']:'';$confirm=is_string($_POST['confirm']??null)?$_POST['confirm']:'';
  if($user==='' || strlen($user)>100 || mb_strlen($pass)<12 || strlen($pass)>72 || $pass!==$confirm)throw new RuntimeException('Utilisateur requis, mot de passe d’au moins 12 caractères (72 octets maximum) et confirmation identique.');
  $lock=fopen($root.'/config/setup.lock','c');if(!$lock || !flock($lock,LOCK_EX))throw new RuntimeException('Verrouillage impossible.');
  if(is_file($configFile))throw new RuntimeException('L’installation est déjà terminée.');
  define('ROOT',$root);require $root.'/app/core.php';require $root.'/app/feeds.php';db();
  $values=['ACTU_ADMIN_USER'=>$user,'ACTU_ADMIN_PASSWORD_HASH'=>password_hash($pass,PASSWORD_DEFAULT)];
  $temp=$configFile.'.'.bin2hex(random_bytes(6)).'.tmp';
  if(file_put_contents($temp,"<?php\nreturn ".var_export($values,true).";\n",LOCK_EX)===false)throw new RuntimeException('Écriture de configuration impossible.');
  chmod($temp,0600);if(!rename($temp,$configFile)){@unlink($temp);throw new RuntimeException('Enregistrement impossible.');}
  @unlink($keyFile);@unlink($root.'/config/install.enabled');flock($lock,LOCK_UN);fclose($lock);$_SESSION=[];session_destroy();
  header('Location: '.$base.'/login.php?installed=1',true,303);exit;
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Initialisation impossible. Vérifiez les extensions PHP et les droits des dossiers privés.';}
}
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installation · Actu Cyber &amp; IT</title><link rel="stylesheet" href="<?=esc($base)?>/assets/app.css"></head><body><main class="container"><section class="page-heading"><p class="eyebrow">BIENVENUE</p><h1>Votre veille, prête à démarrer.</h1><p>Créez votre accès administrateur. Aucun terminal, fichier à modifier ou base MySQL à créer.</p></section><section class="panel"><h2>Vérification de l’hébergement</h2><ul><?php foreach($checks as $label=>$ok):?><li><?= $ok?'✓':'✕' ?> <?=esc($label)?></li><?php endforeach ?></ul><p class="muted">Archive destinée à cPanel avec Apache/LiteSpeed et règles .htaccess actives. Gardez tous les fichiers et le dossier caché .actu lors de la copie.</p></section><?php if($error):?><p class="notice error" role="alert"><?=esc($error)?></p><?php endif ?><form method="post" class="panel login-form"><input type="hidden" name="csrf" value="<?=esc($csrf)?>"><label for="key">Code d’installation</label><input id="key" name="key" autocomplete="off" required><p class="muted">Dans cPanel, activez l’affichage des fichiers cachés et ouvrez .actu/config/install.key. Ce code protège la première installation ; il ne sera plus utilisable ensuite.</p><label for="user">Utilisateur administrateur</label><input id="user" name="user" autocomplete="username" maxlength="100" required><label for="pass">Mot de passe (12 caractères minimum)</label><input id="pass" name="pass" type="password" autocomplete="new-password" minlength="12" maxlength="72" required><label for="confirm">Confirmer le mot de passe</label><input id="confirm" name="confirm" type="password" autocomplete="new-password" minlength="12" required><button <?=in_array(false,$checks,true)?'disabled':''?>>Installer le site</button></form></main></body></html>

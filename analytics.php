<?php
session_start();

// ----- AUTH -----
$USER = getenv('ACTU_ADMIN_USER') ?: '';
$PASS = getenv('ACTU_ADMIN_PASSWORD') ?: '';

if(!isset($_SESSION['admin'])){
    if(isset($_POST['user'],$_POST['pass'])){
        if($USER !== '' && $PASS !== '' && $_POST['user']===$USER && $_POST['pass']===$PASS){
            $_SESSION['admin']=true;
        } else {
            $error="Utilisateur ou mot de passe incorrect";
        }
    } else {
        $error='';
    }

    if(!isset($_SESSION['admin'])){
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Connexion Admin</title>';
        echo '<script src="https://cdn.tailwindcss.com"></script></head><body class="bg-gray-50 dark:bg-gray-900 min-h-screen flex items-center justify-center">';
        echo '<form method="post" class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg w-80">';
        echo '<h2 class="text-xl font-bold mb-4 text-center text-gray-800 dark:text-gray-100">Connexion Admin</h2>';
        if(!empty($error)) echo '<div class="text-red-500 mb-2">'.$error.'</div>';
        echo '<input type="text" name="user" placeholder="Utilisateur" class="w-full mb-3 px-3 py-2 rounded border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-700"/>';
        echo '<input type="password" name="pass" placeholder="Mot de passe" class="w-full mb-3 px-3 py-2 rounded border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-700"/>';
        echo '<button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded">Connexion</button>';
        echo '</form></body></html>'; exit;
    }
}

// ----- CONFIG -----
$TIMEZONE = 'Europe/Paris';
$CACHE_DIR = __DIR__.'/cache';
$MAX_TOP = 10;

// ----- HELPERS -----
function parse_date($str){ if(!$str) return 0; $ts=strtotime($str); return $ts!==false?$ts:0; }
function human_time($ts,$tz){ if($ts<=0) return ''; try{ $dt=new DateTime('@'.$ts); $dt->setTimezone(new DateTimeZone($tz)); return $dt->format('d/m/Y H:i'); }catch(Throwable $e){return '';} }
function ensure_dir($path){ if(!is_dir($path)){ @mkdir($path,0775,true); } }
ensure_dir($CACHE_DIR);

// ----- LOG VISITEURS -----
$logfile = $CACHE_DIR.'/visitors.log';
$visitor = [
    'ip'=>$_SERVER['REMOTE_ADDR']??'',
    'user_agent'=>$_SERVER['HTTP_USER_AGENT']??'',
    'time'=>date('Y-m-d H:i:s'),
];
file_put_contents($logfile,json_encode($visitor).PHP_EOL,FILE_APPEND);

// ----- TOP VULNÉRABILITÉS (depuis cache des vulnérabilités produits) -----
$topVuln = [];
$vulCache = $CACHE_DIR.'/vul_prod.json';
if(file_exists($vulCache)){
    $vul = @json_decode(file_get_contents($vulCache),true);
    if(is_array($vul)){
        usort($vul,function($a,$b){ return $b['date']<=>$a['date']; });
        $topVuln = array_slice($vul,0,$MAX_TOP);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
function updateClock(){
    const el=document.getElementById('clock');
    if(el) el.textContent = new Date().toLocaleString('fr-FR',{hour12:false});
}
setInterval(updateClock,1000);
</script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
<header class="sticky top-0 z-10 backdrop-blur bg-white/70 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700 py-4">
<div class="max-w-6xl mx-auto px-4 flex items-center justify-between">
<h1 class="text-xl font-bold">📊 Analytics Admin</h1>
<a href="admin.php" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Retour Admin</a>
</div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6">
<section class="mb-8">
<h2 class="text-lg font-semibold mb-2">🕒 Horloge en temps réel</h2>
<div id="clock" class="text-2xl font-mono mb-4"></div>
</section>

<section class="mb-8">
<h2 class="text-lg font-semibold mb-2">👁️ Visiteurs récents</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm border border-gray-300 dark:border-gray-700 rounded">
<thead>
<tr class="bg-gray-200 dark:bg-gray-700">
<th class="px-3 py-2 border-b border-gray-300 dark:border-gray-600">IP</th>
<th class="px-3 py-2 border-b border-gray-300 dark:border-gray-600">Navigateur / UA</th>
<th class="px-3 py-2 border-b border-gray-300 dark:border-gray-600">Date / Heure</th>
</tr>
</thead>
<tbody>
<?php
$visitors = array_slice(array_reverse(file($logfile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)),0,50);
foreach($visitors as $v){
    $vdata = json_decode($v,true);
    echo '<tr class="border-b border-gray-300 dark:border-gray-700">';
    echo '<td class="px-3 py-2">'.htmlspecialchars($vdata['ip']??'').'</td>';
    echo '<td class="px-3 py-2">'.htmlspecialchars($vdata['user_agent']??'').'</td>';
    echo '<td class="px-3 py-2">'.htmlspecialchars($vdata['time']??'').'</td>';
    echo '</tr>';
}
?>
</tbody>
</table>
</div>
</section>

<section class="mb-8">
<h2 class="text-lg font-semibold mb-2">🚨 Top vulnérabilités produits</h2>
<div class="grid md:grid-cols-2 gap-4">
<?php foreach($topVuln as $v): ?>
<article class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition">
<h3 class="font-semibold mb-1"><?=htmlspecialchars($v['title'])?></h3>
<p class="text-sm text-gray-700 dark:text-gray-300 line-clamp-3"><?=htmlspecialchars($v['summary'])?></p>
<time class="text-xs text-gray-500 dark:text-gray-400"><?=htmlspecialchars(human_time($v['date'],$TIMEZONE))?></time>
</article>
<?php endforeach; ?>
</div>
</section>
</main>
</body>
</html>

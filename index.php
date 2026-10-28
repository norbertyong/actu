<?php
// index.php — Agrégateur + Top Vulnérabilités + Horloge

ini_set('display_errors', 0);
error_reporting(E_ALL);

$SITE_NAME = 'Actu Cyber & IT';
$CACHE_DIR = __DIR__ . '/cache';
$CACHE_TTL = 20 * 60; 
$TIMEZONE = 'Europe/Paris';
$MAX_ITEMS = 300;

$FEEDS = [
    'C’est qui qui a fuité aujourd’hui ?'=>['https://bonjourlafuite.eu.org/feed.xml','cyber'],
    'The Hacker News'=>['https://feeds.feedburner.com/TheHackersNews','cyber'],
    'Krebs on Security'=>['https://krebsonsecurity.com/feed/','cyber'],
    'BleepingComputer'=>['https://www.bleepingcomputer.com/feed/','cyber'],
    'SecurityWeek'=>['https://www.securityweek.com/rss','cyber'],
    'Project Zero'=>['https://googleprojectzero.blogspot.com/feeds/posts/default?alt=rss','cyber'],
    'Microsoft Security Blog'=>['https://www.microsoft.com/security/blog/feed/','cyber'],
    'CERT-FR (Alertes)'=>['https://www.cert.ssi.gouv.fr/feed/','cyber'],
    'ANSSI (Actus)'=>['https://cyberveille.esante.gouv.fr/alertes-et-vulnerabilites/rss.xml/','cyber'],
    'Ars Technica – Tech'=>['https://feeds.arstechnica.com/arstechnica/technology','it'],
    'ZDNet – News'=>['https://www.zdnet.com/news/rss.xml','it'],
    'Malware Tech'=>['https://www.malwaretech.com/feed/','it'],
    'Wired – Security'=>['https://www.wired.com/feed/category/security/latest/rss','cyber'],
    'GitHub Security Advisories'=>['https://github.com/security-advisories.atom','cyber'],
    'ImperialViolet'=>['https://www.imperialviolet.org/iv-rss.xml','cyber'],
    'cybersmashup'=>['https://research.kudelskisecurity.com/feed/','cyber'],
    'Uncommon Sense Security'=>['http://blog.uncommonsensesecurity.com/feeds/posts/default','cyber'],
'Dark Reading'=>['http://www.darkreading.com/rss/all.xml','cyber'],
'Daemonic Dispatches'=>['http://www.daemonology.net/blog/index.rss','cyber'],
'Schneier on Security'=>['http://feeds.feedburner.com/schneier/fulltext','cyber'],
'Trail of Bits Blog'=>['http://blog.trailofbits.com/feed/','cyber'],
'Irongeek\'s Security Site'=>['http://feeds.feedburner.com/IrongeeksSecuritySite','cyber'],
'MalwareTech'=>['https://www.malwaretech.com/feed/','cyber'],
'Errata Security'=>['http://blog.erratasec.com/feeds/posts/default','cyber'],
'SkullSecurity'=>['http://www.skullsecurity.org/blog/feed','cyber'],
'chrisbrenton.org'=>['http://feeds.feedburner.com/chrisbrenton/VcDP','cyber'],
'Project Zero'=>['http://googleprojectzero.blogspot.com/feeds/posts/default','cyber'],
'Postmodern Security'=>['http://postmodernsecurity.com/category/blog-posts/feed/','cyber'],
'Packet Storm Hacker News'=>['http://rss.packetstormsecurity.com/news/tags/hacker/','cyber'],
'Krebs on Security'=>['https://krebsonsecurity.com/feed/','cyber'],
'Tech Anarchy'=>['https://techanarchy.net/feed/','cyber'],
'Darknet Diaries'=>['http://feeds.feedburner.com/DarknetDiaries','cyber'],
'Bleeping Computer News'=>['https://www.bleepingcomputer.com/feed/','cyber'],
'Threatpost'=>['https://threatpost.com/feed/','cyber'],
'Google Research Blog'=>['http://feeds.feedburner.com/blogspot/gJZg','cyber'],
'nixCraft'=>['http://feeds.cyberciti.biz/Nixcraft-LinuxFreebsdSolarisTipsTricks','linux'],
'doublepulsar'=>['https://doublepulsar.com/feed','cyber'],
];

// ----- HELPERS (comme ton code) -----
function ensure_dir(string $path){if(!is_dir($path)) @mkdir($path,0775,true);}
function http_get_cached(string $url,string $cacheDir,int $ttl){$key=hash('sha256',$url);$file=$cacheDir.'/'.$key.'.xml';if(file_exists($file)&&(time()-filemtime($file))<$ttl){return @file_get_contents($file);} $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'ActuCyberIT/1.0 PHP/'.PHP_VERSION,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.8']]); $data=curl_exec($ch); $httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); if($data && $httpCode>=200 && $httpCode<300){@file_put_contents($file,$data); return $data;} if(file_exists($file)) return @file_get_contents($file); return null;}
function parse_date($str){$ts=strtotime((string)$str);return $ts!==false?$ts:0;}
function normalize_item(array $raw){return ['title'=>trim($raw['title']??''),'link'=>trim($raw['link']??''),'date'=>(int)($raw['date']??0),'author'=>trim($raw['author']??''),'source'=>trim($raw['source']??''),'category'=>trim($raw['category']??''),'summary'=>trim($raw['summary']??''),'id'=>hash('sha1',($raw['link']??'').'|'.($raw['title']??''))];}
function parse_feed(string $xml,string $label,string $category){libxml_use_internal_errors(true);$sx=@simplexml_load_string($xml);if(!$sx) return []; $items=[]; if(isset($sx->channel->item)){foreach($sx->channel->item as $it){$items[]=normalize_item(['title'=>(string)$it->title,'link'=>(string)$it->link,'date'=>parse_date((string)$it->pubDate),'author'=>(string)$it->author,'source'=>$label,'category'=>$category,'summary'=>mb_substr(strip_tags((string)($it->description??'')),0,400)]);} }elseif(isset($sx->entry)){foreach($sx->entry as $it){$link=''; foreach($it->link as $l){$attrs=$l->attributes();if(!$attrs) continue;if((string)$attrs->rel==='alternate'||!isset($attrs->rel)){$link=(string)$attrs->href;break;}} $items[]=normalize_item(['title'=>(string)$it->title,'link'=>$link,'date'=>parse_date((string)($it->updated??$it->published??'')),'author'=>isset($it->author->name)?(string)$it->author->name:'','source'=>$label,'category'=>$category,'summary'=>mb_substr(strip_tags((string)($it->summary??$it->content??'')),0,400)]);} } return $items;}
function human_time(int $ts,string $tz){if($ts<=0) return ''; try{$dt=new DateTime('@'.$ts);$dt->setTimezone(new DateTimeZone($tz)); return $dt->format('d/m/Y H:i');}catch(Throwable $e){return '';}}

// ----- COLLECTE -----
ensure_dir($CACHE_DIR);
$all=[];
foreach($FEEDS as $label=>[$url,$cat]){$xml=http_get_cached($url,$CACHE_DIR,$CACHE_TTL);if(!$xml) continue; $items=parse_feed($xml,$label,$cat); $all=array_merge($all,$items);}
$seen=[];$dedup=[];
foreach($all as $it){if(isset($seen[$it['id']])) continue; $seen[$it['id']]=true; $dedup[]=$it;}
usort($dedup,function($a,$b){return $b['date']<=>$a['date'];});
$dedup=array_slice($dedup,0,$MAX_ITEMS);

// ----- FILTRES -----
$q=trim($_GET['q']??''); $src=trim($_GET['src']??''); $cat=trim($_GET['cat']??'');
$filtered=array_filter($dedup,function($it) use($q,$src,$cat){if($q!='' && mb_strpos(mb_strtolower($it['title'].' '.$it['summary']),mb_strtolower($q))===false) return false; if($src!='' && $it['source']!==$src) return false; if($cat!='' && $it['category']!==$cat) return false; return true;});

// ----- Pagination -----
$page=max(1,(int)($_GET['page']??1)); $perPage=30; $total=count($filtered); $pages=max(1,(int)ceil($total/$perPage));
$items=array_slice(array_values($filtered),($page-1)*$perPage,$perPage);
$sources=array_keys($FEEDS); $cats=array_values(array_unique(array_map(fn($v)=>$v[1],$FEEDS))); sort($cats);

// ----- Top vulnérabilités -----
$VUL_FILE=__DIR__.'/vulprod.log';
$topVuls=[]; if(file_exists($VUL_FILE)) $topVuls=json_decode(file_get_contents($VUL_FILE),true)??[];
$topVuls=array_slice($topVuls,-5); // Top 5

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($SITE_NAME)?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>.line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}</style>
<script>
// Horloge temps réel
function updateClock(){let now=new Date();document.getElementById('clock').textContent=now.toLocaleString('fr-FR');}
setInterval(updateClock,1000); window.onload=updateClock;
</script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
<header class="sticky top-0 z-10 backdrop-blur bg-white/70 dark:bg-gray-900/60 border-b border-gray-200/60 dark:border-gray-800 py-4">
<div class="max-w-6xl mx-auto px-4 flex flex-col md:flex-row items-center gap-3">
<div class="text-2xl font-bold flex-1">🛡️ <?= htmlspecialchars($SITE_NAME) ?></div>
<div id="clock" class="text-sm text-gray-600 dark:text-gray-400"></div>
<form method="get" class="flex gap-2 w-full max-w-xl mt-2 md:mt-0">
<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Rechercher..." class="flex-1 px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700"/>
<select name="cat" class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
<option value="">Toutes catégories</option><?php foreach($cats as $c):?><option value="<?= htmlspecialchars($c) ?>" <?=$cat===$c?'selected':''?>><?= htmlspecialchars(strtoupper($c))?></option><?php endforeach;?>
</select>
<select name="src" class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
<option value="">Toutes sources</option><?php foreach($sources as $s):?><option value="<?= htmlspecialchars($s) ?>" <?=$src===$s?'selected':''?>><?= htmlspecialchars($s)?></option><?php endforeach;?>
</select>
<button class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">Filtrer</button>
</form>
</div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6">
<div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
Résultats: <strong><?= $total ?></strong> <?php if($q):?>• Recherche: "<?= htmlspecialchars($q) ?>"<?php endif;?> <?php if($cat):?>• Catégorie: <?= htmlspecialchars($cat) ?><?php endif;?> <?php if($src):?>• Source: <?= htmlspecialchars($src) ?><?php endif;?>
</div>

<div class="grid md:grid-cols-2 gap-4">
<?php foreach($items as $it):?>
<article class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition">
<div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 flex items-center justify-between">
<span><?= htmlspecialchars($it['source']) ?> • <?= htmlspecialchars(strtoupper($it['category'])) ?></span>
<time><?= htmlspecialchars(human_time($it['date'],$TIMEZONE))?></time>
</div>
<h2 class="mt-2 text-lg font-semibold"><a href="<?= htmlspecialchars($it['link'])?>" target="_blank" rel="noopener noreferrer" class="hover:underline"><?= htmlspecialchars($it['title'])?></a></h2>
<?php if($it['summary']):?><p class="mt-2 text-sm text-gray-700 dark:text-gray-300 line-clamp-3"><?= htmlspecialchars($it['summary'])?></p><?php endif;?>
<?php if($it['author']):?><div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Par <?= htmlspecialchars($it['author'])?></div><?php endif;?>
</article>
<?php endforeach;?>
</div>

<!-- Top vulnérabilités -->
<?php if($topVuls):?>
<section class="mt-10">
<h2 class="text-xl font-bold mb-4">🔥 Top vulnérabilités Produits</h2>
<div class="grid md:grid-cols-2 gap-4">
<?php foreach($topVuls as $v):?>
<div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition">
<h3 class="font-bold"><?= htmlspecialchars($v['produit']) ?> — <?= htmlspecialchars($v['cve']) ?></h3>
<p class="text-gray-600 dark:text-gray-300 text-sm"><?= htmlspecialchars($v['details']) ?></p>
<small class="text-gray-500 dark:text-gray-400"><?= htmlspecialchars($v['date']) ?></small>
</div>
<?php endforeach;?>
</div>
</section>
<?php endif;?>

<!-- Pagination -->
<?php if($pages>1):?>
<nav class="mt-6 flex items-center justify-center gap-2">
<?php for($i=1;$i<=$pages;$i++):?>
<a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="px-3 py-1 rounded <?= $i==$page?'bg-blue-600 text-white':'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100'?>"><?= $i ?></a>
<?php endfor;?>
</nav>
<?php endif;?>

</main>
<footer class="mt-10 py-4 text-center text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700">
© <?= date('Y') ?> — <?= htmlspecialchars($SITE_NAME) ?> — <a href="admin.php" class="underline">Admin</a>
</footer>
</body>
</html>

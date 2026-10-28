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
$SITE_NAME = 'Vulnérabilités Produits';
$CACHE_DIR = __DIR__ . '/cache';
$CACHE_TTL = 20 * 60; 
$TIMEZONE  = 'Europe/Paris';
$MAX_ITEMS = 300;

$FEEDS_FILE = __DIR__.'/feeds.php';
$FEEDS = [];
if(file_exists($FEEDS_FILE)){ include $FEEDS_FILE; }

// ----- HELPERS -----
function ensure_dir(string $path): void { if(!is_dir($path)){ @mkdir($path,0775,true); } }

function http_get_cached(string $url, string $cacheDir, int $ttl): ?string {
    $key = hash('sha256',$url);
    $file = $cacheDir.'/'.$key.'.xml';
    if(file_exists($file) && (time()-filemtime($file))<$ttl){ return @file_get_contents($file); }
    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_USERAGENT=>'ActuCyberIT/1.0 (+https://example.org) PHP/'.PHP_VERSION,
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_HTTPHEADER=>['Accept: application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.8']
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($data && $httpCode>=200 && $httpCode<300){ @file_put_contents($file,$data); return $data; }
    if(file_exists($file)) return @file_get_contents($file);
    return null;
}

function parse_date($str): int { if(!$str) return 0; $ts=strtotime((string)$str); return $ts!==false?$ts:0; }

function normalize_item(array $raw): array {
    return [
        'title'=>trim($raw['title']??''),
        'link'=>trim($raw['link']??''),
        'date'=>(int)($raw['date']??0),
        'author'=>trim($raw['author']??''),
        'source'=>trim($raw['source']??''),
        'category'=>trim($raw['category']??''),
        'summary'=>trim($raw['summary']??''),
        'id'=>hash('sha1',($raw['link']??'').'|'.($raw['title']??'')),
    ];
}

function parse_feed(string $xml,string $label,string $category): array {
    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($xml);
    if(!$sx) return [];
    $items = [];
    // RSS
    if(isset($sx->channel->item)){
        foreach($sx->channel->item as $it){
            $items[] = normalize_item([
                'title' => (string)($it->title ?? ''),
                'link' => (string)($it->link ?? ''),
                'date' => parse_date((string)($it->pubDate ?? '')),
                'author' => (string)($it->author ?? ''),
                'source' => $label,
                'category' => $category,
                'summary' => mb_substr(strip_tags((string)($it->description ?? '')),0,400)
            ]);
        }
    }
    // Atom
    elseif(isset($sx->entry)){
        foreach($sx->entry as $it){
            $link = '';
            if(isset($it->link)){
                foreach($it->link as $l){
                    $attrs=$l->attributes();
                    if(!$attrs) continue;
                    if((string)$attrs->rel==='alternate'||!isset($attrs->rel)){ $link=(string)$attrs->href; break; }
                }
            }
            $items[] = normalize_item([
                'title'=>(string)($it->title??''),
                'link'=>$link,
                'date'=>parse_date((string)($it->updated??$it->published??'')),
                'author'=>isset($it->author->name)?(string)$it->author->name:'',
                'source'=>$label,
                'category'=>$category,
                'summary'=>mb_substr(strip_tags((string)($it->summary??$it->content??'')),0,400)
            ]);
        }
    }
    return $items;
}

function human_time(int $ts,string $tz): string {
    if($ts<=0) return '';
    try{ $dt=new DateTime('@'.$ts); $dt->setTimezone(new DateTimeZone($tz)); return $dt->format('d/m/Y H:i'); }catch(Throwable $e){return'';}
}

// ----- COLLECTE -----
ensure_dir($CACHE_DIR);
$all = [];
foreach($FEEDS as $label=>[$url,$cat]){
    if($cat!=='vulprod') continue; // uniquement vulnérabilités produits
    $xml = http_get_cached($url,$CACHE_DIR,$CACHE_TTL);
    if(!$xml) continue;
    $items = parse_feed($xml,$label,$cat);
    $all = array_merge($all,$items);
}

// Déduplication et tri
$seen=[]; $dedup=[];
foreach($all as $it){ if(isset($seen[$it['id']])) continue; $seen[$it['id']]=true; $dedup[]=$it; }
usort($dedup,function($a,$b){return $b['date']<=>$a['date'];});
$dedup=array_slice($dedup,0,$MAX_ITEMS);

// ----- FILTRES & RECHERCHE -----
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$src = isset($_GET['src']) ? trim((string)$_GET['src']) : '';
$filtered=array_filter($dedup,function($it) use($q,$src){
    if($q!==''){
        $hay=mb_strtolower($it['title'].' '.$it['summary']);
        if(mb_strpos($hay,mb_strtolower($q))===false) return false;
    }
    if($src!=='' && $it['source']!==$src) return false;
    return true;
});

// Pagination
$page=max(1,(int)($_GET['page']??1));
$perPage=30;
$total=count($filtered);
$pages=max(1,(int)ceil($total/$perPage));
$offset=($page-1)*$perPage;
$items=array_slice(array_values($filtered),$offset,$perPage);

// Listes pour filtres
$sources=array_values(array_unique(array_map(fn($v)=>$v[0],$FEEDS)));
sort($sources);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>.line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">

<header class="sticky top-0 z-10 backdrop-blur bg-white/70 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700 py-4">
<div class="max-w-6xl mx-auto px-4 flex items-center justify-between">
<h1 class="text-xl font-bold">🛡️ <?= htmlspecialchars($SITE_NAME) ?></h1>
<a href="admin.php" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Retour Admin</a>
</div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6">
<div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
Résultats: <strong><?= $total ?></strong>
<?php if($q):?> • Recherche: "<?= htmlspecialchars($q) ?>"<?php endif;?>
<?php if($src):?> • Source: <?= htmlspecialchars($src) ?><?php endif;?>
</div>

<form method="get" class="flex flex-wrap gap-2 mb-4">
<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Rechercher..." class="flex-1 px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700"/>
<select name="src" class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
<option value="">Toutes sources</option>
<?php foreach($sources as $s):?>
<option value="<?= htmlspecialchars($s) ?>" <?= $src===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
<?php endforeach;?>
</select>
<button class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">Filtrer</button>
</form>

<div class="grid md:grid-cols-2 gap-4">
<?php foreach($items as $it): ?>
<article class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 shadow-sm hover:shadow-md transition">
<div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 flex items-center justify-between">
<span><?= htmlspecialchars($it['source']) ?></span>
<time><?= htmlspecialchars(human_time($it['date'],$TIMEZONE)) ?></time>
</div>
<h2 class="mt-2 text-lg font-semibold">
<a href="<?= htmlspecialchars($it['link']) ?>" target="_blank" rel="noopener noreferrer" class="hover:underline">
<?= htmlspecialchars($it['title']) ?>
</a>
</h2>
<?php if($it['summary']): ?>
<p class="mt-2 text-sm text-gray-700 dark:text-gray-300 line-clamp-3"><?= htmlspecialchars($it['summary']) ?></p>
<?php endif; ?>
<?php if($it['author']): ?>
<div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Par <?= htmlspecialchars($it['author']) ?></div>
<?php endif; ?>
</article>
<?php endforeach;?>
</div>

<?php if($pages>1): ?>
<nav class="mt-6 flex items-center justify-center gap-2">
<?php for($i=1;$i<=$pages;$i++):
$qs=$_GET; $qs['page']=$i; $url='?'.http_build_query($qs);
$active = $i===$page;
?>
<a href="<?= htmlspecialchars($url) ?>" class="px-3 py-1 rounded-lg border <?php echo $active?'bg-blue-600 text-white border-blue-600':'border-gray-300 dark:border-gray-700'; ?>">
<?= $i ?></a>
<?php endfor;?>
</nav>
<?php endif; ?>

</main>
</body>
</html>

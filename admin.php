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
$FEEDS_FILE = __DIR__.'/feeds.php';
$FEEDS = [];
if(file_exists($FEEDS_FILE)){
    include $FEEDS_FILE;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
<header class="sticky top-0 z-10 backdrop-blur bg-white/70 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700 py-4">
<div class="max-w-6xl mx-auto px-4 flex items-center justify-between">
<h1 class="text-xl font-bold">⚙️ Admin Panel</h1>
<a href="index.php" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Retour Accueil</a>
</div>
</header>

<main class="max-w-6xl mx-auto px-4 py-6 space-y-8">

<section>
<h2 class="text-lg font-semibold mb-2">🔗 Liens rapides</h2>
<div class="flex flex-wrap gap-3">
<a href="analytics.php" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700">Analytics</a>
<a href="vulprod.php" class="px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700">Vulnérabilités Produits</a>
<a href="index.php" class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Voir le site</a>
</div>
</section>

<section>
<h2 class="text-lg font-semibold mb-2">📝 Gestion rapide des flux</h2>
<p class="mb-2">Pour ajouter, éditer ou supprimer un flux, éditez le fichier <code>feeds.php</code> directement :</p>
<div class="overflow-auto bg-gray-100 dark:bg-gray-800 p-3 rounded">
<pre><code>
&lt;?php
$FEEDS = [
<?php
foreach($FEEDS as $label=>$arr){
    $url = $arr[0] ?? '';
    $cat = $arr[1] ?? '';
    echo "    '".addslashes($label)."' => ['".addslashes($url)."','".addslashes($cat)."'],\n";
}
?>
];
</code></pre>
</div>
</section>

<section>
<h2 class="text-lg font-semibold mb-2">📊 Fonctions avancées</h2>
<ul class="list-disc ml-5 space-y-1">
<li>Voir <strong>Analytics</strong> en temps réel : visiteurs, IP, navigateur, pays</li>
<li>Voir <strong>Top vulnérabilités</strong> depuis les flux et vulnérabilités produits</li>
<li>Gérer les flux RSS rapidement via le fichier <code>feeds.php</code></li>
<li>Ajouter des fonctionnalités supplémentaires (alertes, export CSV, notifications…)</li>
</ul>
</section>

</main>
</body>
</html>

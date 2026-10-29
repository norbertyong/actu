<?php
require __DIR__.'/../app/bootstrap.php'; require __DIR__.'/../app/view.php';
$reader = reader(); $f = filters(); $f['period'] = '7'; $f['unread'] = '1';
$tracked = (int) run('SELECT COUNT(*) FROM technologies WHERE reader=?', [$reader])->fetchColumn();
if (!isset($_GET['tracked'])) $f['tracked'] = $tracked ? '1' : '';
[$from, $params] = article_query($f, $reader);
$urgent = "EXISTS(SELECT 1 FROM article_cves ac JOIN cves c ON c.id=ac.cve_id WHERE ac.article_id=a.id AND (c.exploited=1 OR c.cvss>=9))";
$count = (int) run('SELECT COUNT(*)'.$from, $params)->fetchColumn();
$critical = (int) run('SELECT COUNT(*)'.$from.' AND '.$urgent, $params)->fetchColumn();
$articles = run('SELECT a.*,s.name AS source,s.category,COALESCE(st.favorite,0) AS favorite,COALESCE(st.is_read,0) AS is_read'.$from.' ORDER BY ('.$urgent.') DESC,a.published DESC,a.id DESC LIMIT 12', $params)->fetchAll();
page_start('À traiter', 'dashboard.php');
heading('VOTRE VEILLE DU JOUR', 'À traiter', 'Les articles non lus des sept derniers jours. Les mentions de CVE exploitées (KEV) ou critiques (CVSS ≥ 9) passent en premier.');
?>
<div class="results-toolbar"><p><?=$f['tracked']==='1'?'Périmètre : mes technologies':'Périmètre : toutes les technologies'?></p><div class="toolbar-links"><a href="?tracked=1">Mon périmètre</a><a href="?tracked=0">Tout voir</a><a href="<?=e(base_path())?>/technologies.php">Mes technologies (<?=$tracked?>)</a></div></div>
<div class="stats-grid"><a class="stat" href="<?=e(base_path())?>/index.php?<?=e(http_build_query($f))?>"><span>Articles non lus · 7 jours</span><strong><?=$count?></strong></a><div class="stat"><span>Articles KEV ou CVSS ≥ 9 · non lus</span><strong><?=$critical?></strong></div><a class="stat" href="<?=e(base_path())?>/dossiers.php?tracked=<?=e($f['tracked'])?>"><span>Vue regroupée</span><strong>CVE</strong></a>
<?php if (is_admin()): ?><a class="stat" href="<?=e(base_path())?>/alerts.php"><span>Alertes générées · 24 heures · tous sujets</span><strong><?= (int)run('SELECT COUNT(*) FROM notifications WHERE created>=?', [time()-86400])->fetchColumn() ?></strong></a><?php endif ?></div>
<?php if (!$tracked): ?><p class="notice">Ajoutez vos produits dans <a href="<?=e(base_path())?>/technologies.php">Mes technologies</a> pour personnaliser cette sélection.</p><?php endif ?>
<?php if (!$articles): ?><section class="empty"><h2>Aucun article à traiter dans ce périmètre</h2><p>Les articles plus anciens restent dans les actualités. Vérifiez vos technologies et l’état des sources si la collecte n’a pas encore eu lieu.</p></section><?php else: ?><div class="article-grid"><?php foreach ($articles as $article) article_card($article); ?></div><p><a href="<?=e(base_path())?>/index.php?<?=e(http_build_query($f))?>">Voir tous les résultats →</a></p><?php endif ?>
<?php page_end();

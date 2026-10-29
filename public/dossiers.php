<?php
require __DIR__.'/../app/bootstrap.php'; require __DIR__.'/../app/view.php';
$reader = reader(); $f = filters();
[$from, $params] = article_query($f, $reader);
$joined = ' FROM cves c JOIN article_cves ac ON ac.cve_id=c.id JOIN (SELECT a.id,a.source_id,a.published'.$from.') matched ON matched.id=ac.article_id';
$total = (int) run('SELECT COUNT(DISTINCT c.id)'.$joined, $params)->fetchColumn();
$pages = max(1, (int) ceil($total/24)); $page = min($pages, max(1, (int)input('page','1')));
$order = $f['sort']==='severity' ? 'c.cvss DESC,c.exploited DESC,latest DESC,c.id' : 'latest DESC,c.id';
$groups = run('SELECT c.*,COUNT(DISTINCT ac.article_id) AS articles,COUNT(DISTINCT matched.source_id) AS sources,MAX(matched.published) AS latest'.$joined.' GROUP BY c.id ORDER BY '.$order.' LIMIT 24 OFFSET '.(($page-1)*24), $params)->fetchAll();
page_start('Dossiers CVE', 'vulprod.php');
heading('CROISER LES SOURCES', 'Dossiers CVE', 'Un dossier par identifiant CVE, avec toutes les publications associées. Un article qui cite plusieurs CVE peut figurer dans plusieurs dossiers.');
filters_form($f, true);
?><p><?=$total?> dossiers · page <?=$page?> / <?=$pages?> · <a href="<?=e(base_path())?>/vulprod.php?<?=e(http_build_query($f))?>">Vue par article</a></p>
<?php if (!$groups): ?><section class="empty"><h2>Aucun dossier pour ces filtres</h2><p>Les dossiers apparaissent dès qu’une CVE est repérée dans un article collecté.</p></section><?php endif ?>
<div class="article-grid"><?php foreach ($groups as $group): ?>
<article class="article-card"><div class="card-meta"><span class="badge <?=$group['exploited']?'danger':''?>"><?=$group['exploited']?'Exploitation connue · KEV':'Exploitation non renseignée'?></span><span class="badge"><?=$group['cvss']!==null?'CVSS '.e($group['cvss']):'CVSS non renseigné'?></span></div>
<h2><a href="<?=e(base_path())?>/vulprod.php?<?=e(http_build_query(array_merge($f,['cve'=>$group['id']])))?>"><?=e($group['id'])?></a></h2><p><?=e(trim($group['vendor'].' '.$group['product']) ?: 'Produit non renseigné')?></p><p><?=$group['articles']?> article(s) · <?=$group['sources']?> source(s)</p><p class="muted">Dernière publication : <?=e(when((int)$group['latest']))?></p>
<?php if ($group['action']): ?><p>Action indiquée par CISA : <?=e($group['action'])?></p><?php endif ?>
</article><?php endforeach ?></div>
<?php if ($pages>1): ?><nav class="pagination" aria-label="Pagination"><?php if ($page>1): ?><a href="?<?=e(http_build_query(array_merge($f,['page'=>$page-1])))?>">← Précédent</a><?php endif ?><?php if ($page<$pages): ?><a href="?<?=e(http_build_query(array_merge($f,['page'=>$page+1])))?>">Suivant →</a><?php endif ?></nav><?php endif ?>
<?php page_end();

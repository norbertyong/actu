<?php
require __DIR__.'/../app/bootstrap.php'; require_admin();
require __DIR__.'/../app/view.php'; require __DIR__.'/../app/tracking_view.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try { $id=tracking_id(input('cve','',$_POST)); $created=tracking_create($id); flash($created?'Suivi CVE ouvert.':'Cette CVE possède déjà une fiche.'); redirect('/cve.php?id='.rawurlencode($id)); }
    catch(InvalidArgumentException $ex) { http_response_code(400); $error=$ex->getMessage(); }
}
$f=tracking_filters(); [$from,$params]=tracking_query($f);
$total=(int)run('SELECT COUNT(*)'.$from,$params)->fetchColumn(); $pages=max(1,(int)ceil($total/25)); $page=min($pages,max(1,(int)input('page','1')));
$rows=run(tracking_select().$from.' ORDER BY '.tracking_order($f).' LIMIT 25 OFFSET '.(($page-1)*25),$params)->fetchAll();
[$allFrom,$allParams]=tracking_query(tracking_filters([]));
$stats=run("SELECT SUM(w.status IN ('analyze','affected','progress','accepted')) AS open,SUM(".tracking_overdue_sql().") AS overdue,SUM(".tracking_review_sql().") AS review,SUM(w.cve_id IS NULL) AS untracked".$allFrom,$allParams)->fetch();
page_start('Suivi des CVE'); admin_nav('tracking.php');
heading('EXPOSITION & REMÉDIATION','Suivi des CVE','Décisions, responsables et plan d’actions partagés entre administrateurs. Les données internes restent privées.');
?>
<div class="stats-grid"><a class="stat" href="?status=open"><span>Fiches ouvertes</span><strong><?=(int)$stats['open']?></strong></a><a class="stat" href="?overdue=1"><span>Fiches ou actions en retard</span><strong><?=(int)$stats['overdue']?></strong></a><a class="stat" href="?review=1"><span>Fiches à réexaminer</span><strong><?=(int)$stats['review']?></strong></a><a class="stat" href="?status=untracked"><span>CVE sans suivi</span><strong><?=(int)$stats['untracked']?></strong></a></div>
<section class="panel"><h2>Ouvrir un suivi</h2><p>Une fiche unique par CVE, y compris avant sa première publication dans vos flux. Un identifiant saisi manuellement n’est pas vérifié auprès d’un registre externe.</p>
<?php if($error): ?><p class="error-text" role="alert"><?=e($error)?></p><?php endif ?>
<form method="post" class="save-search"><?=csrf_field()?><label for="new-cve">Identifiant CVE</label><input id="new-cve" name="cve" maxlength="64" required placeholder="CVE-2026-12345" value="<?=e(input('cve','',$_POST))?>"><button>Ouvrir le suivi</button></form></section>
<form method="get" class="filter-panel case-filters">
<div><label for="q">CVE, produit ou système</label><input id="q" name="q" value="<?=e($f['q'])?>" maxlength="200" type="search"></div>
<div><label for="status">Statut</label><select id="status" name="status"><?php foreach([''=>'Tous','untracked'=>'Non suivi','open'=>'Toutes les fiches ouvertes',...tracking_statuses()] as $key=>$label): ?><option value="<?=e($key)?>" <?=$key===$f['status']?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
<div><label for="owner">Responsable de fiche ou d’action ouverte</label><input id="owner" name="owner" value="<?=e($f['owner'])?>" maxlength="120"></div>
<div><label for="priority">Priorité interne</label><select id="priority" name="priority"><option value="">Toutes</option><?php foreach(tracking_priorities() as $key=>$label): ?><option value="<?=$key?>" <?=$key===$f['priority']?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
<div><label for="sort">Classement</label><select id="sort" name="sort"><?php foreach(['priority'=>'Priorité interne puis KEV/CVSS','deadline'=>'Échéance de fiche / réexamen','updated'=>'Dernière modification','cvss'=>'CVSS décroissant'] as $key=>$label): ?><option value="<?=$key?>" <?=$key===$f['sort']?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
<div class="filter-bottom"><?php foreach(['kev'=>'KEV uniquement','overdue'=>'En retard','review'=>'À réexaminer','soon'=>'Échéances sous 7 jours'] as $key=>$label): ?><label class="check"><input type="checkbox" name="<?=$key?>" value="1" <?=$f[$key]==='1'?'checked':''?>><?=e($label)?></label><?php endforeach ?><button>Filtrer le suivi</button><a href="<?=e(base_path())?>/tracking.php">Réinitialiser</a></div></form>
<div class="results-toolbar"><p><strong><?=$total?></strong> CVE · page <?=$page?> / <?=$pages?></p><a href="<?=e(base_path())?>/tracking-export.php?<?=e(http_build_query($f))?>">Exporter ce suivi en CSV</a></div>
<?php if(!$rows): ?><section class="empty"><h2>Aucune CVE pour ces critères</h2><p>Élargissez vos filtres ou ouvrez une fiche avec son identifiant.</p></section><?php else: ?><div class="table-wrap case-table" tabindex="0" role="region" aria-label="Tableau de suivi des CVE"><table><thead><tr><th>CVE / produit</th><th>Traitement</th><th>Responsable</th><th>Échéances</th><th>Actions ouvertes</th><th>Signaux publics</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><a href="<?=e(base_path())?>/cve.php?id=<?=e($row['id'])?>"><strong><?=e($row['id'])?></strong></a><small><?=e(trim($row['vendor'].' '.$row['product'])?:'Produit non renseigné')?></small></td><td><div class="case-badges"><?php tracking_badges($row); ?></div></td><td><?=e($row['owner']?:'Non attribué')?></td><td><?=e($row['internal_due']?:'Non définie')?><?php if($row['status']==='accepted'): ?><small>Réexamen : <?=e($row['review_date'])?></small><?php endif ?><?php if($row['next_due']): ?><small>Prochaine action : <?=e($row['next_due'])?></small><?php endif ?></td><td><?=(int)$row['pending']?></td><td><?=$row['exploited']?'KEV · exploitation connue':'KEV non renseigné'?><small><?=$row['cvss']!==null?'CVSS '.e($row['cvss']):'CVSS non renseigné'?></small></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?>
<?php tracking_pagination('page',$page,$pages,$f); ?><p class="muted">Les échéances internes sont indépendantes des dates CISA. Un risque accepté reste ouvert ; sa date de réexamen produit un rappel dans ce tableau. Les compteurs du haut couvrent tout le registre.</p>
<?php page_end();

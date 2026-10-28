<?php
require __DIR__.'/../app/bootstrap.php';require_admin();require __DIR__.'/../app/view.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf();$action=input('action','',$_POST);$id=(int)input('id','0',$_POST);
 try {
  if($action==='save') {
   $name=mb_substr(input('name','',$_POST),0,150);$url=input('url','',$_POST);$cat=input('category','',$_POST);$kind=input('kind','rss',$_POST);
   if(!$name || !safe_link($url) || !in_array($cat,['cyber','it','linux','vulprod'],true) || !in_array($kind,['rss','kev'],true)) throw new InvalidArgumentException('Nom, URL et catégorie valides requis.');
   public_host($url);
   $enabled=input('enabled','',$_POST)==='1'?1:0;
   if($id) run('UPDATE sources SET name=?,url=?,category=?,kind=?,enabled=?,etag=NULL,modified=NULL,checked_at=NULL WHERE id=?',[$name,$url,$cat,$kind,$enabled,$id]);
   else run('INSERT INTO sources(name,url,category,kind,enabled) VALUES(?,?,?,?,?)',[$name,$url,$cat,$kind,$enabled]);
   flash('Source enregistrée. La prochaine collecte prendra en compte ce changement.');redirect('/manage.php');
  } elseif($action==='toggle') { run('UPDATE sources SET enabled=1-enabled WHERE id=?',[$id]);flash('État de la source modifié.');redirect('/manage.php'); }
  elseif($action==='test') {
   $s=run('SELECT * FROM sources WHERE id=?',[$id])->fetch();if(!$s) throw new InvalidArgumentException('Source introuvable.');
   $r=fetch_url($s['url']);
   if($s['kind']==='rss') $n=count(parse_feed($r['body']));else { $j=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);if(!isset($j['vulnerabilities']) || !is_array($j['vulnerabilities'])) throw new InvalidArgumentException('Catalogue KEV invalide.');$n=count($j['vulnerabilities']); }
   flash('Test réussi : '.$n.' entrées reçues. Les articles seront enregistrés par le collecteur.');redirect('/manage.php');
  } elseif($action==='import') {
   if(!isset($_FILES['opml']) || $_FILES['opml']['error']!==UPLOAD_ERR_OK || $_FILES['opml']['size']>1024*1024) throw new InvalidArgumentException('Fichier OPML requis (1 Mo maximum).');
   $n=opml_import(file_get_contents($_FILES['opml']['tmp_name']));flash($n.' sources importées, désactivées par défaut. Vérifiez-les avant activation.');redirect('/manage.php');
  }
 } catch(PDOException $e) { $error='Enregistrement impossible : cette URL existe peut-être déjà.'; }
 catch(Throwable $e) { $error=$e->getMessage(); }
}
$edit=run('SELECT * FROM sources WHERE id=?',[(int)input('edit','0')])->fetch()?:['id'=>0,'name'=>'','url'=>'','category'=>'cyber','kind'=>'rss','enabled'=>1];
page_start('Gestion des sources');admin_nav('manage.php');heading('CONFIGURATION','Des sources maîtrisées.','Une liste commune pour les actualités et les vulnérabilités. Les sources désactivées conservent leur historique.');
if($error):?><p class="notice error" role="alert"><?=e($error)?></p><?php endif ?>
<section class="panel"><h2><?=$edit['id']?'Modifier une source':'Ajouter une source'?></h2><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit['id']?>"><div><label for="name">Nom</label><input id="name" name="name" value="<?=e($edit['name'])?>" required maxlength="150"></div><div><label for="url">URL publique du flux</label><input id="url" type="url" name="url" value="<?=e($edit['url'])?>" required></div><div><label for="category">Catégorie</label><select id="category" name="category"><?php foreach(['cyber','it','linux','vulprod'] as $c):?><option <?=$edit['category']===$c?'selected':''?>><?=$c?></option><?php endforeach ?></select></div><div><label for="kind">Format</label><select id="kind" name="kind"><option value="rss" <?=$edit['kind']==='rss'?'selected':''?>>RSS / Atom</option><option value="kev" <?=$edit['kind']==='kev'?'selected':''?>>CISA KEV (JSON)</option></select></div><label class="check"><input type="checkbox" name="enabled" value="1" <?=$edit['enabled']?'checked':''?>> Source active</label><div><button>Enregistrer</button> <a href="<?=e(base_path())?>/manage.php">Nouveau</a></div></form></section>
<section class="panel"><h2>Sources configurées</h2><div class="table-wrap"><table><thead><tr><th>Source</th><th>Catégorie</th><th>État</th><th>Dernier succès</th><th>Actions</th></tr></thead><tbody><?php foreach(run('SELECT * FROM sources ORDER BY name')->fetchAll() as $s):?><tr><td><strong><?=e($s['name'])?></strong><small class="url"><?=e($s['url'])?></small><?php if($s['error']):?><small class="error-text"><?=e($s['error'])?></small><?php endif ?></td><td><?=e($s['category'])?></td><td><?=$s['enabled']?'Active':'Désactivée'?></td><td><?=e(when($s['success_at']?(int)$s['success_at']:null))?></td><td><a href="?edit=<?=$s['id']?>">Modifier</a><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$s['id']?>"><button class="text-button" name="action" value="toggle"><?=$s['enabled']?'Désactiver':'Activer'?></button><button class="text-button" name="action" value="test">Tester</button></form></td></tr><?php endforeach ?></tbody></table></div></section>
<section class="panel"><h2>Importer / exporter OPML</h2><p>L’import accepte jusqu’à 100 flux RSS/Atom et ignore les URL déjà présentes. Les sources importées sont désactivées jusqu’à votre validation. Le catalogue KEV ne fait pas partie de l’export OPML.</p><a class="button secondary" href="<?=e(base_path())?>/opml.php">Exporter les sources RSS</a><form method="post" enctype="multipart/form-data" class="stack"><?=csrf_field()?><input type="hidden" name="action" value="import"><label for="opml">Fichier OPML</label><input id="opml" type="file" name="opml" accept=".opml,.xml" required><button>Importer</button></form></section><?php page_end();

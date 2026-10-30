<?php
require __DIR__.'/../app/bootstrap.php'; require_admin();
require __DIR__.'/../app/view.php'; require __DIR__.'/../app/tracking_view.php';
try { $id=tracking_id(input('id')); } catch(InvalidArgumentException $error) { http_response_code(400); exit('Identifiant CVE invalide.'); }
$cve=run('SELECT * FROM cves WHERE id=?',[$id])->fetch();
if(!$cve) { http_response_code(404); exit('CVE absente. Ouvrez son suivi depuis le tableau de suivi des CVE.'); }
$error=''; $conflict=false; $action='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $action=input('action','',$_POST);
    try {
        $revision=filter_var(input('revision','0',$_POST),FILTER_VALIDATE_INT);
        if($revision===false || $revision<0) throw new InvalidArgumentException('Révision invalide. Rechargez la fiche.');
        switch($action) {
            case 'create': tracking_create($id); break;
            case 'save': tracking_save($id,$revision,$_POST); break;
            case 'comment': tracking_comment($id,$revision,input('comment','',$_POST)); break;
            case 'task': tracking_task($id,$revision,$_POST); break;
            case 'review':
                $signal=filter_var(input('signal','',$_POST),FILTER_VALIDATE_INT);
                if($signal===false || $signal<0) throw new InvalidArgumentException('Version des informations invalide.');
                tracking_review($id,$revision,$signal); break;
            default: throw new InvalidArgumentException('Action inconnue.');
        }
        flash('Suivi CVE enregistré.'); redirect('/cve.php?id='.rawurlencode($id).($action==='task'?'#actions':($action==='comment'?'#history':'')));
    } catch(TrackingConflict $ex) { http_response_code(409); $conflict=true; $error=$ex->getMessage(); }
    catch(InvalidArgumentException $ex) { http_response_code(400); $error=$ex->getMessage(); }
}
[$cve,$case]=tracking_snapshot($id);
$revisionCase=$case;
// A validation error must not upgrade an old submitted revision to the current one.
if($error && $case) $revisionCase['revision']=(int)input('revision','0',$_POST);
$url=base_path().'/cve.php?id='.rawurlencode($id);
page_start($id.' · Suivi'); admin_nav('tracking.php');
heading('FICHE DE VULNÉRABILITÉ',$id,'Publications associées, décisions et actions internes. Une mention dans un flux ne prouve pas que votre installation est vulnérable.');
?>
<p><a href="<?=e(base_path())?>/tracking.php">← Tableau de suivi</a> · <a href="<?=e(base_path())?>/vulprod.php?cve=<?=e($id)?>">Articles dans le fil public</a><?php if($case): ?> · <a href="<?=e(base_path())?>/tracking-export.php?format=markdown&amp;id=<?=e($id)?>">Télécharger la fiche Markdown</a><?php endif ?></p>
<?php if($error): ?><p class="notice error-text" role="alert"><?=e($error)?></p><?php endif ?>
<?php if($conflict): ?><section class="panel"><h2>Saisie non enregistrée</h2><p>Copiez votre saisie ci-dessous avant de recharger. La version enregistrée par l’autre opération a été conservée.</p><details open><summary>Votre saisie</summary><dl class="case-changes"><?php foreach($_POST as $key=>$value): if(!is_string($value) || in_array($key,['csrf','revision','signal'],true)) continue; ?><dt><?=e(tracking_fields()[$key]??$key)?></dt><dd class="preserve-lines"><?=e($value)?></dd><?php endforeach ?></dl></details><a class="button" href="<?=e($url)?>">Recharger la fiche</a></section><?php endif ?>
<section class="panel"><div class="card-meta"><span class="badge <?=$cve['exploited']?'danger':''?>"><?=$cve['exploited']?'Exploitation connue · KEV':'Exploitation non renseignée'?></span><span class="badge"><?=$cve['cvss']!==null?'CVSS '.e($cve['cvss']):'CVSS non renseigné'?></span></div><h2><?=e(trim($cve['vendor'].' '.$cve['product'])?:'Produit non renseigné')?></h2>
<p><a href="https://www.cve.org/CVERecord?id=<?=e($id)?>" target="_blank" rel="noopener noreferrer">Registre CVE ↗</a> · <a href="https://nvd.nist.gov/vuln/detail/<?=e($id)?>" target="_blank" rel="noopener noreferrer">Fiche NVD ↗</a></p>
<?php if($cve['action']): ?><p>Action indiquée par CISA : <?=e($cve['action'])?></p><?php endif ?><?php if($cve['due_date']): ?><p>Date CISA : <?=e($cve['due_date'])?> — distincte de votre échéance interne.</p><?php endif ?>
<?php if(!$case): ?><p>Aucun suivi interne n’est ouvert pour cette CVE.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create"><button>Commencer le suivi</button></form><?php else: ?><div class="case-badges"><?php tracking_badges($case); ?></div><p class="muted">Suivi depuis <?=e(when((int)$case['created_at']))?> · Mis à jour <?=e(when((int)$case['updated_at']))?><?php if($case['closed_at']): ?> · Clôture <?=e(when((int)$case['closed_at']))?><?php endif ?></p><?php endif ?></section>
<?php if($case && (int)$case['signal_version']>(int)$case['reviewed_signal']): ?><section class="panel case-review"><h2>Nouvelles informations à réexaminer</h2><p>Des articles associés ou des données KEV/CVSS ont changé depuis le dernier examen. Votre décision reste « <?=e(tracking_statuses()[$case['status']])?> ». Consultez les publications ci-dessous avant de confirmer.</p><?php if(!$conflict): ?><form method="post"><?=csrf_field()?><?=tracking_revision_field($revisionCase)?><input type="hidden" name="action" value="review"><input type="hidden" name="signal" value="<?=$case['signal_version']?>"><button class="secondary">Confirmer l’examen des informations</button></form><?php endif ?></section><?php endif ?>
<?php if($case && $case['status']==='accepted' && $case['review_date']<=date('Y-m-d')): ?><p class="notice">Le risque accepté doit être réexaminé. Actualisez la décision et sa date de réexamen ; confirmer la lecture des publications ne renouvelle pas cette acceptation.</p><?php endif ?>
<?php if($case && !$conflict): $form=$case; if($action==='save' && $error) foreach(tracking_fields() as $key=>$label) $form[$key]=input($key,(string)$case[$key],$_POST); ?>
<section class="panel"><h2>Décision et périmètre</h2><form method="post" class="form-grid case-form" id="case-edit"><?=csrf_field()?><?=tracking_revision_field($revisionCase)?><input type="hidden" name="action" value="save">
<div><label for="case-status">Statut de traitement</label><select id="case-status" name="status"><?php foreach(tracking_statuses() as $key=>$label): ?><option value="<?=$key?>" <?=$form['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
<div><label for="case-priority">Priorité interne</label><select id="case-priority" name="priority"><?php foreach(tracking_priorities() as $key=>$label): ?><option value="<?=$key?>" <?=$form['priority']===$key?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
<div><label for="case-owner">Responsable de la fiche</label><input id="case-owner" name="owner" maxlength="120" value="<?=e($form['owner'])?>"><small>Nom ou équipe ; aucune invitation ni notification n’est envoyée.</small></div>
<div><label for="case-due">Échéance interne</label><input id="case-due" name="due_date" type="date" value="<?=e($form['due_date'])?>"></div>
<div><label for="case-review">Date de réexamen du risque accepté</label><input id="case-review" name="review_date" type="date" value="<?=e($form['review_date'])?>"></div>
<div><label for="case-ticket">Lien du ticket</label><input id="case-ticket" name="ticket_url" type="url" maxlength="2000" value="<?=e($form['ticket_url'])?>"></div>
<div class="full-width"><label for="case-scope">Systèmes et versions concernés</label><textarea id="case-scope" name="scope" rows="4" maxlength="6000" placeholder="Système, version installée, exposition et résultat de vérification"><?=e($form['scope'])?></textarea></div>
<div class="full-width"><label for="case-mitigation">Mesures et plan de remédiation</label><textarea id="case-mitigation" name="mitigation" rows="4" maxlength="6000"><?=e($form['mitigation'])?></textarea></div>
<div class="full-width"><label for="case-decision">Justification de la décision</label><textarea id="case-decision" name="decision" rows="3" maxlength="4000"><?=e($form['decision'])?></textarea><small>Obligatoire pour Corrigé, Non concerné et Risque accepté. Documentez le contrôle effectué et son résultat.</small></div>
<div class="full-width"><label for="case-evidence">Lien justificatif</label><input id="case-evidence" name="evidence_url" type="url" maxlength="2000" value="<?=e($form['evidence_url'])?>"><small>Lien vers votre preuve ou document interne. Aucun fichier n’est téléchargé par le site.</small></div>
<div class="full-width"><label for="case-reason">Motif du changement de décision</label><textarea id="case-reason" name="change_note" rows="2" maxlength="2000"><?=e($action==='save'?input('change_note','',$_POST):'')?></textarea><small>Obligatoire pour changer une décision Corrigé, Non concerné ou Risque accepté.</small></div>
<div class="full-width"><button>Enregistrer la fiche</button></div></form>
<?php if($case['ticket_url'] || $case['evidence_url']): ?><p><?php foreach(['ticket_url'=>'Ouvrir le ticket','evidence_url'=>'Ouvrir le justificatif'] as $key=>$label): if(!$case[$key]) continue; ?><a href="<?=e($case[$key])?>" target="_blank" rel="noopener noreferrer"><?=e($label)?> ↗</a> <?php endforeach ?></p><?php endif ?></section>
<?php endif ?>
<?php if($case):
$taskCount=(int)run('SELECT COUNT(*) FROM cve_tasks WHERE cve_id=?',[$id])->fetchColumn(); $taskPages=max(1,(int)ceil($taskCount/20)); $taskPage=min($taskPages,max(1,(int)input('tasks_page','1')));
$tasks=run("SELECT * FROM cve_tasks WHERE cve_id=? ORDER BY status='todo' DESC,NULLIF(due_date,'') IS NULL,due_date,id LIMIT 20 OFFSET ".(($taskPage-1)*20),[$id])->fetchAll();
?>
<section class="panel" id="actions"><h2>Plan d’actions (<?=$taskCount?>)</h2>
<?php if(!$tasks): ?><p>Aucune action enregistrée.</p><?php else: ?><div class="table-wrap" tabindex="0" role="region" aria-label="Plan d’actions"><table><thead><tr><th>Action</th><th>Responsable</th><th>Échéance</th><th>État</th><th>Gestion</th></tr></thead><tbody><?php foreach($tasks as $task): ?><tr><td><?=e($task['title'])?></td><td><?=e($task['owner']?:'Non attribué')?></td><td><?=e($task['due_date']?:'Non définie')?><?php if($task['status']==='todo' && $task['due_date']!=='' && $task['due_date']<date('Y-m-d')): ?><span class="badge danger">En retard</span><?php endif ?></td><td><?=e(['todo'=>'À faire','done'=>'Terminée','cancelled'=>'Annulée'][$task['status']])?></td><td><?php if(!$conflict && !in_array($case['status'],['fixed','unaffected'],true)): ?><a href="<?=e($url)?>&amp;task=<?=$task['id']?>#task-form">Modifier</a><?php else: ?>—<?php endif ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
<?php tracking_pagination('tasks_page',$taskPage,$taskPages,['id'=>$id],'#actions'); ?>
<?php if(!$conflict && !in_array($case['status'],['fixed','unaffected'],true)):
$taskEdit=run('SELECT * FROM cve_tasks WHERE id=? AND cve_id=?',[(int)input('task','0'),$id])->fetch()?:['id'=>0,'title'=>'','owner'=>$case['owner'],'due_date'=>'','status'=>'todo'];
if($action==='task' && $error) $taskEdit=['id'=>(int)input('task_id','0',$_POST),'title'=>input('title','',$_POST),'owner'=>input('owner','',$_POST),'due_date'=>input('due_date','',$_POST),'status'=>input('task_status','todo',$_POST)];
?>
<h3><?=$taskEdit['id']?'Modifier l’action #'.(int)$taskEdit['id']:'Ajouter une action'?></h3>
<form method="post" class="form-grid case-form" id="task-form"><?=csrf_field()?><?=tracking_revision_field($revisionCase)?><input type="hidden" name="action" value="task"><input type="hidden" name="task_id" value="<?=(int)$taskEdit['id']?>">
<div class="full-width"><label for="task-title">Action à réaliser</label><input id="task-title" name="title" required maxlength="240" value="<?=e($taskEdit['title'])?>"></div>
<div><label for="task-owner">Responsable de l’action</label><input id="task-owner" name="owner" maxlength="120" value="<?=e($taskEdit['owner'])?>"></div>
<div><label for="task-due">Échéance de l’action</label><input id="task-due" name="due_date" type="date" value="<?=e($taskEdit['due_date'])?>"></div>
<div><label for="task-status">État de l’action</label><select id="task-status" name="task_status"><?php foreach(['todo'=>'À faire','done'=>'Terminée','cancelled'=>'Annulée'] as $key=>$label): ?><option value="<?=$key?>" <?=$taskEdit['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
<div><label for="task-reason">Motif ou résultat de l’action</label><textarea id="task-reason" name="change_note" rows="2" maxlength="2000"><?=e($action==='task'?input('change_note','',$_POST):'')?></textarea><small>Obligatoire pour annuler une action.</small></div>
<div class="full-width"><button>Enregistrer l’action</button><?php if($taskEdit['id']): ?> <a href="<?=e($url)?>#actions">Nouvelle action</a><?php endif ?></div></form>
<?php elseif(in_array($case['status'],['fixed','unaffected'],true)): ?><p>Rouvrez la fiche pour modifier son plan d’actions.</p><?php endif ?></section>
<?php endif ?>
<section class="panel" id="publications"><h2>Publications associées</h2>
<?php $articleCount=(int)run('SELECT COUNT(*) FROM article_cves WHERE cve_id=?',[$id])->fetchColumn(); $articlePages=max(1,(int)ceil($articleCount/10)); $articlePage=min($articlePages,max(1,(int)input('articles_page','1')));
$articles=run('SELECT a.*,s.name AS source FROM articles a JOIN sources s ON s.id=a.source_id JOIN article_cves ac ON ac.article_id=a.id WHERE ac.cve_id=? ORDER BY a.published DESC,a.id DESC LIMIT 10 OFFSET '.(($articlePage-1)*10),[$id])->fetchAll(); ?>
<?php if(!$articles): ?><p>Aucune publication collectée pour cet identifiant. Vérifiez sa référence dans le registre CVE. Le suivi interne reste conservé.</p><?php else: ?><ul class="case-publications"><?php foreach($articles as $article): ?><li><a href="<?=e($article['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($article['title'])?> ↗</a><p class="muted"><?=e($article['source'])?> · <?=e(when((int)$article['published']))?> · <a href="<?=e(base_path())?>/ai.php?id=<?=$article['id']?>">Résumé IA de l’article</a></p><p><?=e($article['summary'])?></p></li><?php endforeach ?></ul><?php endif ?>
<?php tracking_pagination('articles_page',$articlePage,$articlePages,['id'=>$id],'#publications'); ?></section>
<?php if($case):
$historyCount=(int)run('SELECT COUNT(*) FROM cve_events WHERE cve_id=?',[$id])->fetchColumn(); $historyPages=max(1,(int)ceil($historyCount/15)); $historyPage=min($historyPages,max(1,(int)input('history_page','1')));
$events=run('SELECT * FROM cve_events WHERE cve_id=? ORDER BY id DESC LIMIT 15 OFFSET '.(($historyPage-1)*15),[$id])->fetchAll();
?><section class="panel" id="history"><h2>Notes et historique (<?=$historyCount?>)</h2><p class="muted">Historique horodaté, non modifiable depuis l’interface. Les actions sont attribuées au compte administrateur connecté ; les responsables sont des champs d’organisation.</p>
<?php if(!$conflict): ?><form method="post" class="case-form"><?=csrf_field()?><?=tracking_revision_field($revisionCase)?><input type="hidden" name="action" value="comment"><label for="comment">Ajouter une note interne</label><textarea id="comment" name="comment" required maxlength="6000" rows="3"><?=e($action==='comment'?input('comment','',$_POST):'')?></textarea><button>Ajouter la note</button></form><?php endif ?>
<?php tracking_history($events); tracking_pagination('history_page',$historyPage,$historyPages,['id'=>$id],'#history'); ?></section><?php endif ?>
<?php page_end();

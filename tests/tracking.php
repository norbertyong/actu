<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/actu-tracking-'.bin2hex(random_bytes(6)); putenv('ACTU_STORAGE='.$dir);
require __DIR__.'/../app/bootstrap.php';
$checks=0;
function check(bool $value,string $label): void { global $checks; if(!$value) throw new RuntimeException('ÉCHEC : '.$label); $checks++; echo 'OK '.$label.PHP_EOL; }
function rejects(callable $operation,string $label,string $class=InvalidArgumentException::class): void {
    try { $operation(); } catch(Throwable $error) { if($error instanceof $class) { check(true,$label); return; } throw $error; }
    throw new RuntimeException('ÉCHEC : '.$label);
}
function current_revision(string $id): int { return (int)tracking_case($id)['revision']; }
function case_count(array $filters): int { [$from,$params]=tracking_query(tracking_filters($filters)); return (int)run('SELECT COUNT(*)'.$from,$params)->fetchColumn(); }
function update_case(string $id,array $values): void { tracking_save($id,current_revision($id),array_merge(tracking_case($id),$values)); }
try {
    rejects(fn()=>tracking_create('CVE-2026-10001'),'Mutation sans administrateur refusée',RuntimeException::class);
    $_SESSION=['admin_time'=>time(),'admin_user'=>'Analyste test'];
    check(tracking_id(' cve-2026-10001 ')==='CVE-2026-10001','Identifiant normalisé');
    foreach(['CVE-26-1234','CVE-2026-1',"CVE-2026-10001' OR 1=1",str_repeat('C',100)] as $invalid) rejects(fn()=>tracking_create($invalid),'Identifiant invalide refusé');
    $id='CVE-2026-10001'; $other='CVE-2026-10002';
    check(tracking_create($id),'Ouverture manuelle avant publication');
    check(!tracking_create(strtolower($id)) && (int)run('SELECT COUNT(*) FROM cve_events')->fetchColumn()===1,'Ouverture idempotente sans doublon d’historique');
    check(tracking_case($id)['status']==='analyze','État initial à analyser');
    tracking_create($other);
    check(case_count(['status'=>'open'])===2,'Filtre des fiches ouvertes');
    rejects(fn()=>tracking_date('2026-02-30'),'Date impossible refusée');
    rejects(fn()=>tracking_date('2026-1-2'),'Date non canonique refusée');
    check(tracking_date('2028-02-29')==='2028-02-29','Année bissextile acceptée');
    foreach(['javascript:alert(1)','data:text/html,x','https://user:pass@example.org','https://example.org/\n'] as $link) {
        if(str_contains($link,'\\n')) $link=str_replace('\\n',"\n",$link);
        rejects(fn()=>tracking_link($link),'Lien dangereux refusé');
    }
    check(tracking_link('https://tickets.intranet.local/42')==='https://tickets.intranet.local/42','Lien interne autorisé sans téléchargement');
    rejects(fn()=>update_case($id,['status'=>'bad']),'Statut inconnu refusé');
    rejects(fn()=>update_case($id,['owner'=>str_repeat('a',121)]),'Taille des champs bornée');
    update_case(strtolower($id),['status'=>'affected','priority'=>'urgent','owner'=>'Équipe Système','scope'=>'Serveur ÉDITEUR 8.2','due_date'=>date('Y-m-d',strtotime('-1 day'))]);
    check(case_count(['owner'=>'système','q'=>'éditeur','priority'=>'urgent','overdue'=>'1'])===1,'Filtres combinés Unicode et retard');
    check(case_count(['q'=>'%_'])===0,'Recherche littérale sans jokers SQL');
    $revision=current_revision($id);
    tracking_comment($id,$revision,'Vérification locale sans données publiques.');
    rejects(fn()=>tracking_save($id,$revision,array_merge(tracking_case($id),['owner'=>'Écrasement'])),'Ancien formulaire refusé',TrackingConflict::class);
    check(tracking_case($id)['owner']==='Équipe Système','Conflit préserve la dernière version');
    $events=(int)run('SELECT COUNT(*) FROM cve_events WHERE cve_id=?',[$id])->fetchColumn();
    update_case($id,[]);
    check((int)run('SELECT COUNT(*) FROM cve_events WHERE cve_id=?',[$id])->fetchColumn()===$events,'Enregistrement identique sans bruit d’historique');
    tracking_task($id,current_revision($id),['title'=>'Déployer le correctif','owner'=>'Exploitation','due_date'=>date('Y-m-d',strtotime('-2 days'))]);
    $task=run('SELECT * FROM cve_tasks WHERE cve_id=?',[$id])->fetch();
    check(case_count(['owner'=>'Exploitation'])===1,'Filtre inclut le responsable d’une action ouverte');
    rejects(fn()=>tracking_task($other,current_revision($other),['task_id'=>(string)$task['id'],'title'=>'Intrusion']),'Action d’une autre CVE refusée');
    rejects(fn()=>update_case($id,['status'=>'fixed']),'Clôture sans justification refusée');
    $before=current_revision($id);
    rejects(fn()=>update_case($id,['status'=>'fixed','decision'=>'Correctif installé']),'Clôture avec action ouverte refusée');
    check(current_revision($id)===$before && tracking_case($id)['status']==='affected','Refus atomique sans état partiel');
    rejects(fn()=>tracking_task($id,current_revision($id),['task_id'=>(string)$task['id'],'title'=>$task['title'],'task_status'=>'cancelled']),'Annulation sans motif refusée');
    tracking_task($id,current_revision($id),['task_id'=>(string)$task['id'],'title'=>$task['title'],'owner'=>$task['owner'],'due_date'=>$task['due_date'],'task_status'=>'done','change_note'=>'Version vérifiée après redémarrage']);
    update_case($id,['status'=>'fixed','decision'=>'Correctif installé et version contrôlée','evidence_url'=>'https://tickets.example.org/proof/42']);
    check(tracking_case($id)['closed_at']!==null && case_count(['overdue'=>'1'])===0,'Clôture horodatée et suppression des retards résolus');
    rejects(fn()=>tracking_task($id,current_revision($id),['title'=>'Nouvelle tâche']),'Action bloquée sur fiche clôturée');
    rejects(fn()=>update_case($id,['status'=>'progress']),'Réouverture sans motif refusée');
    update_case($id,['status'=>'progress','change_note'=>'Correctif incomplet sur un second serveur']);
    check(tracking_case($id)['closed_at']===null,'Réouverture documentée');
    rejects(fn()=>update_case($other,['status'=>'accepted','decision'=>'Mesure compensatoire']),'Risque accepté sans responsable ni réexamen refusé');
    update_case($other,['status'=>'accepted','decision'=>'Service isolé, exception approuvée','owner'=>'RSSI','review_date'=>date('Y-m-d',strtotime('+2 days'))]);
    check(case_count(['soon'=>'1','status'=>'accepted'])===1,'Rappel de réexamen à sept jours');
    run('UPDATE cve_cases SET review_date=? WHERE cve_id=?',[date('Y-m-d'),$other]);
    check(case_count(['review'=>'1','status'=>'accepted'])===1,'Acceptation arrivée à échéance signalée');
    tracking_review($other,current_revision($other),0);
    check(case_count(['review'=>'1','status'=>'accepted'])===1,'Lecture des publications ne renouvelle pas le risque accepté');
    $source=(int)run('SELECT id FROM sources LIMIT 1')->fetchColumn();
    $item=['title'=>$id.' correctif','summary'=>'Publication initiale','url'=>'https://example.org/cve-case-test','author'=>'Test','published'=>time()];
    $articleId=save_article($source,$item);
    check((int)tracking_case($id)['signal_version']===1 && case_count(['review'=>'1','q'=>$id])===1,'Nouvelle publication déclenche un réexamen');
    save_article($source,$item);
    check((int)tracking_case($id)['signal_version']===1,'Collecte identique sans faux réexamen');
    $revision=current_revision($id); tracking_signal($id);
    rejects(fn()=>tracking_review($id,$revision,1),'Impossible d’acquitter des informations non vues',TrackingConflict::class);
    tracking_review($id,current_revision($id),2);
    check(case_count(['review'=>'1','q'=>$id])===0,'Examen explicite acquitté et historisé');
    tracking_comment($id,current_revision($id),'Commentaire sans action sur les signaux');
    check((int)tracking_case($id)['reviewed_signal']===2,'Notes indépendantes des signaux');
    $item['summary']='Nouvelle version du correctif'; save_article($source,$item);
    check((int)tracking_case($id)['signal_version']===3,'Correction d’un article détectée');
    $nvd=json_encode(['vulnerabilities'=>[['cve'=>['id'=>$id,'metrics'=>['cvssMetricV31'=>[['cvssData'=>['baseScore'=>9.8]]]]]]]]);
    import_cvss($id,$nvd); $signal=(int)tracking_case($id)['signal_version'];
    check($signal===4,'Changement CVSS signalé');
    import_cvss($id,$nvd);
    check((int)tracking_case($id)['signal_version']===$signal,'Même score CVSS sans nouveau signal');
    $kev=json_encode(['vulnerabilities'=>[['cveID'=>$id,'vendorProject'=>'Éditeur','product'=>'Produit','requiredAction'=>'Appliquer le correctif','dueDate'=>'2027-01-02','dateAdded'=>'2026-01-01']]]);
    import_kev($source,$kev); $signal=(int)tracking_case($id)['signal_version'];
    check($signal>4 && tracking_case($id)['due_date']===date('Y-m-d',strtotime('-1 day')),'KEV signale sans écraser l’échéance interne');
    import_kev($source,$kev);
    check((int)tracking_case($id)['signal_version']===$signal,'Réimport KEV inchangé sans signal');
    update_case($id,['status'=>'fixed','decision'=>'Tous les systèmes corrigés']);
    $item['summary']='Nouvelle information après clôture'; save_article($source,$item);
    check(tracking_case($id)['status']==='fixed' && case_count(['review'=>'1','status'=>'fixed'])===1,'Nouvelle publication conserve la décision clôturée');
    $item['title']='Publication corrigée sans référence'; $item['summary']='CVE retirée'; save_article($source,$item);
    check(tracking_case($id)!==null,'Retrait de référence conserve la fiche');
    run('DELETE FROM sources WHERE id=?',[$source]);
    check(tracking_case($id)!==null && (int)run('SELECT COUNT(*) FROM cve_events WHERE cve_id=?',[$id])->fetchColumn()>0,'Suppression des publications conserve le suivi et son historique');
    foreach(['=HYPERLINK("x")'," \t+1",'@SUM(1)','-1'] as $cell) check(str_starts_with(tracking_csv_cell($cell),"'"),'Formule CSV neutralisée');
    check(tracking_csv_cell('CVE-2026-10001')==='CVE-2026-10001','Identifiant CSV intact');
    check(run('SELECT actor FROM cve_events WHERE cve_id=? ORDER BY id LIMIT 1',[$id])->fetchColumn()==='Analyste test','Auteur issu de la session');
    // Two processes submit the same revision. Exactly one note may be committed.
    $revision=current_revision($id);
    $code='require '.var_export(realpath(__DIR__.'/../app/bootstrap.php'),true).';$_SESSION=["admin_time"=>time(),"admin_user"=>"Test concurrence"];try{tracking_comment('.var_export($id,true).','.$revision.',"Note concurrente");exit(0);}catch(TrackingConflict $e){exit(3);}';
    $children=[]; for($i=0;$i<2;$i++) $children[]=proc_open([PHP_BINARY,'-r',$code],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    $codes=[]; foreach($children as $child) $codes[]=proc_close($child); sort($codes);
    check($codes===[0,3] && current_revision($id)===$revision+1,'Concurrence réelle : une seule écriture et un conflit');
    $_SESSION=[];
    rejects(fn()=>tracking_comment($id,current_revision($id),'Interdit'),'Session expirée ne peut plus modifier',RuntimeException::class);
    echo 'PASS : '.$checks.' contrôles du suivi CVE.'.PHP_EOL;
} finally { foreach(glob($dir.'/*')?:[] as $file) if(is_file($file)) unlink($file); if(is_dir($dir)) rmdir($dir); }

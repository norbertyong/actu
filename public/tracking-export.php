<?php
require __DIR__.'/../app/bootstrap.php'; require_admin();

if(input('format')==='markdown') {
    try { $id=tracking_id(input('id')); } catch(InvalidArgumentException $ex) { http_response_code(400); exit('Identifiant CVE invalide.'); }
    $case=tracking_case($id); $cve=run('SELECT * FROM cves WHERE id=?',[$id])->fetch();
    if(!$case || !$cve) { http_response_code(404); exit('Fiche de suivi absente.'); }
    header('Content-Type: text/plain; charset=utf-8'); header('Content-Disposition: attachment; filename="'.$id.'-suivi.md"');
    $block=static function(string $text): string {
        preg_match_all('/`+/', $text, $matches);
        $longest=0; foreach($matches[0] as $run) $longest=max($longest,strlen($run));
        $fence=str_repeat('`',max(3,$longest+1));
        return $fence."text\n".$text."\n".$fence."\n\n";
    };
    echo '# '.$id." — suivi interne\n\nExport privé du ".date(DATE_ATOM).". À partager uniquement avec les personnes autorisées.\n\n";
    echo 'KEV : '.($cve['exploited']?'exploitation connue':'non renseigné').' · CVSS : '.($cve['cvss']??'non renseigné')."\n\n";
    echo "## Produit et action CISA\n\n".$block(trim($cve['vendor'].' '.$cve['product'])."\n".$cve['action']);
    foreach(tracking_fields() as $key=>$label) {
        $value=match($key) {'status'=>tracking_statuses()[$case[$key]],'priority'=>tracking_priorities()[$case[$key]],default=>$case[$key]};
        echo '## '.$label."\n\n".$block($value===''?'Non renseigné':$value);
    }
    echo "## Plan d’actions (1 000 maximum)\n\n";
    foreach(run('SELECT * FROM cve_tasks WHERE cve_id=? ORDER BY id LIMIT 1000',[$id])->fetchAll() as $task) {
        echo $block('#'.$task['id'].' '.$task['title']."\nÉtat : ".['todo'=>'À faire','done'=>'Terminée','cancelled'=>'Annulée'][$task['status']]."\nResponsable : ".$task['owner']."\nÉchéance : ".$task['due_date']);
    }
    echo "## Publications associées (1 000 maximum)\n\n";
    foreach(run('SELECT a.title,a.url FROM articles a JOIN article_cves ac ON ac.article_id=a.id WHERE ac.cve_id=? ORDER BY a.published DESC,a.id DESC LIMIT 1000',[$id])->fetchAll() as $article) echo $block($article['title']."\n".$article['url']);
    echo "## Historique (1 000 derniers événements maximum)\n\n";
    foreach(run('SELECT * FROM cve_events WHERE cve_id=? ORDER BY id DESC LIMIT 1000',[$id])->fetchAll() as $event) {
        echo $block(when((int)$event['created_at']).' · '.$event['actor'].' · '.$event['kind']."\n".$event['note']."\n".json_encode(json_decode($event['changes'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    exit;
}
$f=tracking_filters(); [$from,$params]=tracking_query($f);
$total=(int)run('SELECT COUNT(*)'.$from,$params)->fetchColumn();
if($total>10000) { http_response_code(422); header('Content-Type: text/plain; charset=utf-8'); exit('Plus de 10 000 CVE : précisez les filtres pour exporter un résultat complet.'); }
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="actu-suivi-cve.csv"');
$out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['CVE','Éditeur','Produit','KEV','CVSS','Statut','Priorité interne','Responsable','Échéance interne','Réexamen','Actions ouvertes','En retard','À réexaminer','Systèmes et versions','Plan de remédiation','Justification','Justificatif','Ticket','Mise à jour'],';','"','');
$statement=run(tracking_select().$from.' ORDER BY '.tracking_order($f),$params);
while($row=$statement->fetch()) {
    $values=[$row['id'],$row['vendor'],$row['product'],$row['exploited']?'Oui':'Non renseigné',$row['cvss'],tracking_statuses()[$row['status']??'']??'Non suivi',tracking_priorities()[$row['priority']??'']??'',
        $row['owner'],$row['internal_due'],$row['review_date'],$row['pending'],$row['is_overdue']?'Oui':'Non',$row['needs_review']?'Oui':'Non',$row['scope'],$row['mitigation'],$row['decision'],$row['evidence_url'],$row['ticket_url'],$row['updated_at']?date(DATE_ATOM,(int)$row['updated_at']):''];
    fputcsv($out,array_map('tracking_csv_cell',$values),';','"','');
}
fclose($out);

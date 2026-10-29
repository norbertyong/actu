<?php
declare(strict_types=1);
$dir = sys_get_temp_dir().'/actu-evolution-'.bin2hex(random_bytes(6));
putenv('ACTU_STORAGE='.$dir); putenv('ACTU_GROQ_API_KEY');
require __DIR__.'/../app/bootstrap.php';
$count = 0;
function check(bool $condition, string $label): void {
    global $count;
    if (!$condition) throw new RuntimeException('ÉCHEC : '.$label);
    $count++; echo 'OK '.$label.PHP_EOL;
}
function rejects(callable $call, string $label): void {
    try { $call(); } catch (AiException|InvalidArgumentException $error) { check(true,$label); return; }
    throw new RuntimeException('ÉCHEC : '.$label);
}
function ids(array $filters, string $reader): array {
    [$from,$params]=article_query(array_merge(['q'=>'','cat'=>'','src'=>'','period'=>'','unread'=>'','kev'=>''], $filters),$reader);
    return run('SELECT a.id'.$from.' ORDER BY a.id',$params)->fetchAll(PDO::FETCH_COLUMN);
}
try {
    $source = (int)run('SELECT id FROM sources LIMIT 1')->fetchColumn();
    $one = save_article($source, ['title'=>'Correctif ÉDITEUR CVE-2026-12345','summary'=>'FortiOS : appliquer le correctif.','url'=>'https://example.org/one','author'=>'Test','published'=>time()]);
    $two = save_article($source, ['title'=>'Deuxième source CVE-2026-12345','summary'=>'Produit non précisé.','url'=>'https://example.org/two','author'=>'Test','published'=>time()]);
    $three = save_article($source, ['title'=>'Autre sujet','summary'=>'Sans CVE.','url'=>'https://example.org/three','author'=>'Test','published'=>time()]);
    add_technology('reader-a', 'éditeur'); add_technology('reader-a','ÉDITEUR');
    check((int)run('SELECT COUNT(*) FROM technologies WHERE reader=?',['reader-a'])->fetchColumn()===1,'Technologies Unicode sans doublon');
    check(ids(['tracked'=>'1'],'reader-a')===[$one], 'Correspondance Unicode sur les titres');
    check(ids(['tracked'=>'1'],'reader-b')===[], 'Technologies isolées entre lecteurs');
    add_technology('reader-b','%_');
    check(ids(['tracked'=>'1'],'reader-b')===[], 'Caractères SQL traités littéralement');
    run("UPDATE cves SET product='WordPress',vendor='Éditeur',exploited=1,cvss=9.8 WHERE id='CVE-2026-12345'");
    add_technology('reader-c', 'wordpress');
    check(ids(['tracked'=>'1'],'reader-c')===[$one,$two], 'Correspondance sur le produit KEV et regroupement');
    check(ids(['cve'=>'CVE-2026-12345'],'reader-a')===[$one,$two], 'Dossier CVE avec tous les articles');
    check(count(ids(['cve'=>"CVE-2026-12345' OR 1=1 --"],'reader-a'))===0,'Filtre CVE paramétré');
    run('INSERT INTO states VALUES(?,?,0,1)',['reader-c',$one]);
    check(ids(['tracked'=>'1','unread'=>'1'],'reader-c')===[$two],'Tableau à traiter respecte les articles lus');
    rejects(fn()=>add_technology('reader-a','x'), 'Technologie trop courte refusée');
    for($i=0;$i<29;$i++) add_technology('reader-a','Produit '.$i);
    rejects(fn()=>add_technology('reader-a','Un produit de trop'),'Limite des technologies');
    $health=['enabled'=>1,'error'=>null,'success_at'=>time(),'last_article'=>time()];
    check(source_health($health)[0]==='À jour','Source saine');
    check(source_health(array_replace($health,['last_article'=>null]))[0]==='Aucune publication récente','Flux accessible mais silencieux');
    check(source_health(array_replace($health,['success_at'=>time()-3601]))[0]==='Collecte en retard','Collecte périmée');
    check(source_health(array_replace($health,['error'=>'HTTP 500']))[0]==='Collecte en erreur','Erreur de source prioritaire');
    check(source_health(array_replace($health,['enabled'=>0,'error'=>'HTTP 500']))[0]==='Désactivée','Source inactive distincte');
    collector_event('started','123');
    set_setting('collector_web_checked','456');
    check(setting('collector_cli_started')==='123' && setting('collector_cli_completed')==='','Collecte web distincte du passage CLI terminé');

    $article=run('SELECT * FROM articles WHERE id=?',[$one])->fetch(); $calls=0;
    $result=['summary'=>'Résumé français du correctif.','products'=>['FortiOS'],'actions'=>['Appliquer le correctif mentionné.'],'uncertainties'=>'La version exacte manque dans cet extrait.'];
    $response=['choices'=>[['finish_reason'=>'stop','message'=>['content'=>json_encode($result)]]]];
    $mock=function($payload,$key) use (&$calls,$response) {$calls++; return $response;};
    rejects(fn()=>ai_generate($article,$mock),'IA désactivée par défaut sans appel');
    check($calls===0 && (int)ai_usage()['requests']===0,'IA désactivée sans consommation');
    $saved=['enabled'=>'1','model'=>'llama-3.3-70b-versatile','daily_requests'=>'2','daily_tokens'=>'100000','key'=>'test_fake_key_for_offline_tests_only'];
    ai_save_config($saved);
    check((fileperms($dir.'/groq.json') & 0777)===0600,'Clé locale privée avec permissions 0600');
    $generated=ai_generate($article,$mock);
    check($generated['result']===$result && $calls===1,'Contrat Groq : résumé structuré enregistré');
    ai_generate($article,$mock);
    check($calls===1 && (int)ai_usage()['requests']===1,'Cache évite une deuxième facturation');
    $payload=ai_payload(array_replace($article,['title'=>str_repeat('T',500),'summary'=>str_repeat('S',8000)]),$saved['model']);
    $content=json_decode($payload['messages'][1]['content'],true);
    check(strlen($content['title'])===400 && strlen($content['excerpt'])===6000,'Données transmises bornées');
    check(count($content)===2 && !str_contains(json_encode($payload),$saved['key']),'Aucune clé ou préférence dans le prompt');
    ai_save_config(array_replace($saved,['key'=>'','model'=>'other-chat-model']));
    check(ai_config()['key']===$saved['key'],'Champ clé vide conserve le secret');
    check(ai_cached($article,'other-chat-model')===null,'Changement de modèle invalide le cache');
    ai_generate($article,$mock);
    $article['summary']='Texte modifié';
    check(ai_cached($article,'other-chat-model')===null,'Changement du contenu invalide le cache');
    rejects(fn()=>ai_generate($article,$mock),'Quota de requêtes bloque avant le transport');
    check($calls===2 && (int)ai_usage()['requests']===2,'Quota ne déborde pas');
    run('DELETE FROM ai_usage');
    rejects(fn()=>ai_generate($article,function(){throw new AiException('Erreur simulée');}),'Erreur fournisseur récupérable');
    check((int)ai_usage()['requests']===1 && ai_cached($article,'other-chat-model')===null,'Échec compté sans faux résumé');
    foreach ([['choices'=>[]],['choices'=>[['finish_reason'=>'length']]],['choices'=>[['finish_reason'=>'stop','message'=>['content'=>'not json']]]],['choices'=>[['finish_reason'=>'stop','message'=>['content'=>'{"summary":[],"products":{},"actions":null}']]]]] as $bad) {
        rejects(fn()=>ai_parse($bad),'Réponse IA invalide refusée');
    }
    $html=$result; $html['summary']='<script>alert(1)</script>';
    $parsed=ai_parse(['choices'=>[['finish_reason'=>'stop','message'=>['content'=>json_encode($html)]]]]);
    check(!str_contains(e($parsed['summary']),'<script>'),'Sortie IA échappée comme du texte');
    rejects(fn()=>ai_save_config(array_replace($saved,['key'=>"fake\r\nInjected: header"])), 'Injection dans la clé refusée');
    rejects(fn()=>ai_save_config(array_replace($saved,['daily_requests'=>'99999'])), 'Quota déraisonnable refusé');
    putenv('ACTU_GROQ_API_KEY=test_environment_key_not_persisted');
    ai_save_config(array_replace($saved,['key'=>'','delete_key'=>'1']));
    check(ai_config()['key']==='test_environment_key_not_persisted' && !str_contains(file_get_contents($dir.'/groq.json'),'test_environment_key_not_persisted'),'Clé environnement prioritaire jamais recopiée sur disque');
    putenv('ACTU_GROQ_API_KEY');
    check(ai_config()['key']==='','Suppression effective de la clé locale');
    run('DELETE FROM ai_usage');
    rejects(fn()=>ai_reserve(['daily_requests'=>20,'daily_tokens'=>15000],15001),'Budget prudent bloque les demandes trop grandes');
    check((int)ai_usage()['requests']===0,'Réservation refusée sans consommation');
    // Competing PHP processes exercise the SQLite transaction, not just a mocked counter.
    $children=[];
    $code='require '.var_export(realpath(__DIR__.'/../app/bootstrap.php'),true).';try{ai_reserve(["daily_requests"=>2,"daily_tokens"=>100000],1000);exit(0);}catch(AiException $e){exit(3);}';
    for($i=0;$i<5;$i++) $children[]=proc_open([PHP_BINARY,'-r',$code],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    $success=0; foreach($children as $child) if(proc_close($child)===0) $success++;
    check($success===2 && (int)ai_usage()['requests']===2, 'Quota atomique face à cinq requêtes concurrentes');
    save_article($source, ['title'=>'Correction sans CVE','summary'=>'La référence a été retirée.','url'=>'https://example.org/two','author'=>'Test','published'=>time()]);
    check(article_cves($two)===[],'Correction éditoriale retire la CVE du dossier');
    run('DELETE FROM articles WHERE id=?',[$one]);
    check((int)run('SELECT COUNT(*) FROM ai_cache')->fetchColumn()===0,'Suppression article nettoie son résumé IA');
    echo 'PASS : '.$count.' contrôles des évolutions et de Groq simulé.'.PHP_EOL;
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($dir)) rmdir($dir);
}

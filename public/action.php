<?php
require __DIR__.'/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405);exit; }
verify_csrf();$reader=reader();$action=input('action','',$_POST);
if (in_array($action,['favorite','read'],true)) {
    $id=(int)input('article','0',$_POST);
    if (!run('SELECT 1 FROM articles WHERE id=?',[$id])->fetchColumn()) { http_response_code(404);exit; }
    $col=$action==='favorite'?'favorite':'is_read';
    run('INSERT INTO states(reader,article_id,'.$col.') VALUES(?,?,1) ON CONFLICT(reader,article_id) DO UPDATE SET '.$col.'=1-'.$col,[$reader,$id]);flash($action==='favorite'?'Favoris mis à jour.':'État de lecture mis à jour.');
} elseif ($action==='save_search') {
    $name=mb_substr(input('name','',$_POST),0,80);$f=json_decode(input('filters','{}',$_POST),true);
    if (!$name || !is_array($f)) { http_response_code(400);exit; }
    if ((int)run('SELECT COUNT(*) FROM searches WHERE reader=?',[$reader])->fetchColumn()>=30) { flash('Limite de 30 recherches atteinte.');redirect('/index.php'); }
    $clean=[];foreach(['q','cat','src','period','unread','kev','scope','sort'] as $k) $clean[$k]=isset($f[$k]) && is_string($f[$k])?mb_substr($f[$k],0,200):'';
    run('INSERT INTO searches(reader,name,filters) VALUES(?,?,?)',[$reader,$name,json_encode($clean)]);flash('Recherche enregistrée sur ce navigateur.');
} elseif ($action==='delete_search') { run('DELETE FROM searches WHERE id=? AND reader=?',[(int)input('id','0',$_POST),$reader]);flash('Recherche supprimée.'); }
$back=$_SERVER['HTTP_REFERER']??'';$path=parse_url($back,PHP_URL_PATH);$query=parse_url($back,PHP_URL_QUERY);
$base=base_path();if($base!=='' && is_string($path) && str_starts_with($path,$base.'/'))$path=substr($path,strlen($base));
if (!in_array($path,['/index.php','/vulprod.php','/favorites.php','/'],true)) $path='/index.php';
redirect($path.($query?'?'.$query:''));

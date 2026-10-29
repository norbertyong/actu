<?php
require __DIR__.'/../app/bootstrap.php';require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();session_write_close();header('Content-Type: application/json; charset=utf-8');
db();$lock=fopen(store_dir().'/collect.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)){http_response_code(409);echo json_encode(['error'=>'Une collecte est déjà en cours.']);exit;}
set_setting('collector_web_checked', (string)time());
try {
 $source=run('SELECT * FROM sources WHERE enabled=1 AND (checked_at IS NULL OR checked_at<?) ORDER BY COALESCE(checked_at,0),id LIMIT 1',[time()-1200])->fetch();
 if(!$source){echo json_encode(['done'=>true]);exit;}
 $count=collect_source($source,true);generate_notifications();
 echo json_encode(['done'=>false,'source'=>$source['name'],'count'=>$count,'error'=>$count<0?run('SELECT error FROM sources WHERE id=?',[$source['id']])->fetchColumn():null]);
}finally{flock($lock,LOCK_UN);fclose($lock);}

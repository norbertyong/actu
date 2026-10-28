<?php
require __DIR__.'/../app/bootstrap.php';$reader=reader();$f=filters();$scope=input('scope');[$from,$params]=article_query($f,$reader,$scope==='favorites',$scope==='vuln');$rows=run('SELECT a.*,s.name AS source,s.category'.$from.' ORDER BY '.article_order($f).' LIMIT 1000',$params)->fetchAll();
if(input('format')==='csv') {
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="actu-articles.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Titre','Source','Catégorie','Date','Lien','Résumé'],';','"','');
 foreach($rows as $a) { $values=[$a['title'],$a['source'],$a['category'],date(DATE_ATOM,(int)$a['published']),$a['url'],$a['summary']];foreach($values as &$v) if(preg_match('/^[\s]*[=+@-]/u',$v)) $v="'".$v;unset($v);fputcsv($out,$values,';','"',''); }fclose($out);
} else {
 header('Content-Type: application/rss+xml; charset=utf-8');$xml=new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel/></rss>');$c=$xml->channel;$c->addChild('title','Actu Cyber &amp; IT — veille filtrée');$c->addChild('link','https://github.com/norbertyong/actu');$c->addChild('description','Articles correspondant aux filtres sélectionnés (1000 maximum).');
 foreach($rows as $a) { $i=$c->addChild('item');foreach(['title'=>$a['title'],'link'=>$a['url'],'description'=>$a['summary'],'guid'=>$a['guid'],'pubDate'=>date(DATE_RSS,(int)$a['published'])] as $k=>$v) $i->addChild($k,htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8'));$i->guid->addAttribute('isPermaLink','false'); }echo $xml->asXML();
}

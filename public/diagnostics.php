<?php
require __DIR__.'/../app/bootstrap.php'; require_admin(); require __DIR__.'/../app/view.php';
$started = (int)setting('collector_cli_started'); $completed = (int)setting('collector_cli_completed');
$cron = !$started ? 'Aucun lancement CLI observé' : ($started > $completed ? 'Dernier lancement non terminé (en cours ou interrompu)' : ($completed < time()-3600 ? 'Aucun passage CLI terminé depuis plus d’une heure' : 'Passage CLI récent'));
$sources = run('SELECT s.*,MAX(a.published) AS last_article,COUNT(a.id) AS count FROM sources s LEFT JOIN articles a ON a.source_id=s.id GROUP BY s.id ORDER BY s.enabled DESC,s.name')->fetchAll();
page_start('Diagnostic des collectes'); admin_nav('diagnostics.php');
heading('FIABILITÉ DE LA VEILLE', 'Diagnostic des collectes', 'Distinguez une source silencieuse, une collecte en erreur et un collecteur qui ne passe plus.');
?>
<section class="panel"><h2><?=e($cron)?></h2>
<p>Dernier début CLI : <?=e(when($started ?: null))?> · Dernière fin CLI : <?=e(when($completed ?: null))?></p>
<?php if ($completed): ?><p>Sources en échec lors du dernier passage terminé : <?=(int)setting('collector_cli_errors','0')?></p><?php endif ?>
<p>Dernière demande depuis l’administration : <?=e(when((int)setting('collector_web_checked') ?: null))?></p>
<p>Un passage CLI peut être manuel ou lancé par cron. Le site observe les exécutions ; il ne peut pas vérifier la configuration de cPanel. Planifiez <code>bin/collect.php</code> toutes les 20 minutes. Une collecte web ne confirme pas le fonctionnement du cron.</p><a href="<?=e(base_path())?>/admin.php">Lancer une collecte manuelle et voir la commande cron →</a></section>
<p>« Collecte en retard » : aucun succès depuis une heure. « Aucune publication récente » : collecte réussie, mais aucun article daté des sept derniers jours ; cela peut être normal pour une source peu active.</p>
<div class="source-grid"><?php foreach ($sources as $source): [$label,$class] = source_health($source); ?>
<article class="panel source-card"><span class="badge <?=e($class)?>"><?=e($label)?></span><h2><?=e($source['name'])?></h2>
<p>Dernière tentative : <?=e(when($source['checked_at']?(int)$source['checked_at']:null))?></p>
<p>Dernier succès : <?=e(when($source['success_at']?(int)$source['success_at']:null))?></p>
<p>Dernière publication : <?=e(when($source['last_article']?(int)$source['last_article']:null))?> · <?=$source['count']?> article(s)</p>
<?php if ($source['error']): ?><p class="error-text"><?=e($source['error'])?></p><?php endif ?>
<a href="<?=e(base_path())?>/manage.php?edit=<?=$source['id']?>">Configurer la source</a></article>
<?php endforeach ?></div>
<?php page_end();

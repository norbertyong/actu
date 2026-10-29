<?php
require __DIR__.'/../app/bootstrap.php'; require_admin(); require __DIR__.'/../app/view.php';
$id = (int)input('id','0');
$article = run('SELECT a.*,s.name AS source FROM articles a JOIN sources s ON s.id=a.source_id WHERE a.id=?', [$id])->fetch();
if (!$article) { http_response_code(404); exit('Article introuvable.'); }
$error = ''; $config = ai_config(); $cached = ai_cached($article, $config['model']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); session_write_close();
    try { ai_generate($article); redirect('/ai.php?id='.$id); }
    catch (AiException $ex) { $error = $ex->getMessage(); }
}
page_start('Résumé IA');
heading('LECTURE ASSISTÉE', 'Résumé IA', 'Une synthèse de l’extrait disponible, à vérifier dans la publication originale.');
?>
<section class="panel"><h2><?=e($article['title'])?></h2><p><?=e($article['source'])?> · <a href="<?=e($article['url'])?>" target="_blank" rel="noopener noreferrer">Lire la publication originale ↗</a></p><details><summary>Extrait utilisé</summary><p><?=e(mb_substr(strip_tags($article['summary']),0,6000))?></p></details></section>
<?php if ($error): ?><p class="notice error-text" role="alert"><?=e($error)?></p><?php endif ?>
<?php if ($cached): $result = $cached['result']; ?>
<section class="panel ai-result"><p class="eyebrow">SYNTHÈSE GÉNÉRÉE PAR IA</p><p><?=e($result['summary'])?></p>
<h2>Produits mentionnés</h2><?php if (!$result['products']): ?><p>Non précisés dans l’extrait.</p><?php else: ?><ul><?php foreach ($result['products'] as $value): ?><li><?=e($value)?></li><?php endforeach ?></ul><?php endif ?>
<h2>Actions mentionnées dans l’extrait</h2><?php if (!$result['actions']): ?><p>Aucune action explicitement mentionnée.</p><?php else: ?><ul><?php foreach ($result['actions'] as $value): ?><li><?=e($value)?></li><?php endforeach ?></ul><?php endif ?>
<h2>Limites et points à vérifier</h2><p><?=e($result['uncertainties'])?></p><p class="muted">Groq · <?=e($cached['model'])?> · <?=e(when($cached['created']))?>. Résultat en cache, aucun nouvel appel à l’affichage. Vérifiez les recommandations auprès de la source.</p></section>
<?php else: ?><section class="panel"><h2>Résumer cet article en français</h2><p>Cette action transmet le titre et l’extrait affiché à Groq. Elle consomme une requête du quota et peut être facturée sur votre compte Groq. Le texte complet de la page n’est pas téléchargé.</p>
<?php if ($config['enabled'] && $config['key']!==''): ?><form method="post" class="ai-generate"><?=csrf_field()?><button>Générer le résumé</button><span class="ai-progress" role="status" aria-live="polite"></span></form><?php else: ?><p><a href="<?=e(base_path())?>/settings.php">Configurer et activer Groq →</a></p><?php endif ?></section><?php endif ?>
<p><a href="<?=e(base_path())?>/index.php">Retour aux actualités</a> · <a href="<?=e(base_path())?>/settings.php">Paramètres IA</a></p>
<?php page_end();

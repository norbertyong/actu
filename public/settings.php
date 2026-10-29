<?php
require __DIR__.'/../app/bootstrap.php'; require_admin(); require __DIR__.'/../app/view.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (input('action','',$_POST) === 'clear_cache') { ai_clear_cache(); flash('Résumés IA supprimés.'); }
        else { ai_save_config($_POST); flash('Configuration Groq enregistrée.'); }
        redirect('/settings.php');
    } catch (AiException $ex) { $error = $ex->getMessage(); }
}
$config = ai_config(); $usage = ai_usage();
page_start('Paramètres IA'); admin_nav('settings.php');
heading('ASSISTANT DE VEILLE', 'Paramètres IA · Groq', 'Résumés en français à la demande, depuis les extraits des articles. Aucun appel automatique pendant la collecte.');
?>
<?php if ($error): ?><p class="notice error-text" role="alert"><?=e($error)?></p><?php endif ?>
<section class="panel"><h2>Connexion à Groq</h2>
<p>Clé : <strong><?=$config['key']!==''?'configurée':'absente'?></strong>. <?=getenv('ACTU_GROQ_API_KEY')?'La variable serveur ACTU_GROQ_API_KEY est prioritaire sur la clé locale.':''?></p>
<form method="post" class="form-grid"><?=csrf_field()?>
<div><label for="groq-key">Nouvelle clé API Groq</label><input id="groq-key" type="password" name="key" autocomplete="new-password" maxlength="256" placeholder="Laisser vide pour conserver la clé"><p class="muted">Stockée dans un fichier privé, jamais renvoyée au navigateur. Utilisez HTTPS.</p></div>
<div><label for="model">Modèle Groq</label><input id="model" name="model" value="<?=e($config['model'])?>" maxlength="120" required><p class="muted">Identifiant d’un modèle de conversation compatible JSON disponible sur votre compte Groq.</p></div>
<div><label for="daily-requests">Requêtes maximum par jour</label><input id="daily-requests" type="number" name="daily_requests" min="1" max="200" value="<?=$config['daily_requests']?>" required></div>
<div><label for="daily-tokens">Budget quotidien en unités prudentes</label><input id="daily-tokens" type="number" name="daily_tokens" min="15000" max="2000000" value="<?=$config['daily_tokens']?>" required></div>
<label class="check"><input type="checkbox" name="enabled" value="1" <?=$config['enabled']?'checked':''?>> Activer les résumés Groq</label>
<label class="check"><input type="checkbox" name="delete_key" value="1"> Supprimer la clé enregistrée localement</label>
<div><button>Enregistrer les paramètres IA</button></div></form></section>
<section class="panel"><h2>Consommation et confidentialité</h2>
<p>Aujourd’hui (UTC) : <strong><?=$usage['requests']?> / <?=$config['daily_requests']?></strong> requêtes et <strong><?=$usage['reserved_tokens']?> / <?=$config['daily_tokens']?></strong> unités réservées.</p>
<p>Chaque tentative réserve un budget calculé sur la taille de la demande, avec une marge pour la réponse. Les échecs comptent aussi. Ce compteur prudent n’est ni le nombre réel de tokens facturés, ni un plafond en euros : consultez Groq pour la consommation et les tarifs réels. Remise à zéro à minuit UTC.</p>
<p>Seul un administrateur peut demander et consulter un résumé IA. Le titre (400 caractères maximum) et l’extrait RSS (6 000 caractères maximum) sont transmis à Groq lorsque vous cliquez sur « Générer le résumé ». Vos technologies suivies, cookies et notes personnelles ne sont pas transmis. Le résultat est conservé localement et réutilisé tant que le texte et le modèle ne changent pas.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="clear_cache"><button class="secondary">Supprimer les résumés IA</button></form>
<p>Pour vérifier la connexion, ouvrez un article puis « Résumé IA » et lancez une génération. Un message explique les erreurs de clé, de modèle ou de quota.</p></section>
<?php page_end();

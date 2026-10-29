<?php
require __DIR__.'/../app/bootstrap.php';
require __DIR__.'/../app/view.php';
$reader = reader(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (input('action', '', $_POST) === 'delete') {
            run('DELETE FROM technologies WHERE id=? AND reader=?', [(int) input('id', '0', $_POST), $reader]);
        } else { add_technology($reader, input('name', '', $_POST)); }
        flash('Technologies suivies mises à jour.'); redirect('/technologies.php');
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
}
$technologies = run('SELECT * FROM technologies WHERE reader=? ORDER BY name', [$reader])->fetchAll();
page_start('Mes technologies', 'dashboard.php');
heading('VOTRE PÉRIMÈTRE', 'Mes technologies', 'Retrouvez les articles qui mentionnent vos produits. Cette sélection reste propre à ce navigateur.');
?>
<section class="panel">
<p>Ajoutez un nom précis par entrée : FortiOS, WordPress, Windows Server… La correspondance porte sur le texte des articles et les produits du catalogue KEV, sans distinction de casse. Elle aide au tri mais ne prouve pas que votre version est vulnérable.</p>
<?php if ($error): ?><p class="error-text" role="alert"><?=e($error)?></p><?php endif ?>
<form method="post" class="save-search"><?=csrf_field()?>
<label for="technology">Produit ou expression</label><input id="technology" name="name" minlength="2" maxlength="80" required placeholder="Ex. FortiOS">
<button>Suivre cette technologie</button></form>
<p class="muted"><?=count($technologies)?> / 30 technologies suivies.</p>
<?php foreach ($technologies as $technology): ?>
<div class="saved-row"><strong><?=e($technology['name'])?></strong>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$technology['id']?>"><button class="text-button" aria-label="<?=e('Retirer '.$technology['name'])?>">Retirer</button></form></div>
<?php endforeach ?>
<p><a href="<?=e(base_path())?>/dashboard.php">À traiter →</a> · <a href="<?=e(base_path())?>/index.php?tracked=1">Articles de mes technologies →</a></p>
</section>
<?php page_end();

<?php
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/view.php';page_start('Versions');heading('ÉVOLUTION DU SITE','Versions & changements.','Le fichier VERSION, le tag Git et les notes de version identifient les livraisons.');?><section class="panel prose"><pre class="release-notes"><?=e(file_get_contents(ROOT.'/RELEASE_NOTES.md'))?></pre></section><?php page_end();

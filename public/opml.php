<?php
require __DIR__.'/../app/bootstrap.php';require_admin();header('Content-Type: text/x-opml; charset=utf-8');header('Content-Disposition: attachment; filename="actu-sources.opml"');echo opml_export();

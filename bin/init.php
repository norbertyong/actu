<?php
require __DIR__ . "/../app/bootstrap.php";
db();
echo "Base initialisée : " .
    run("SELECT COUNT(*) FROM sources")->fetchColumn() .
    " sources configurées.\n";

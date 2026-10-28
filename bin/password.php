<?php
if (PHP_SAPI !== "cli") {
    exit();
}
fwrite(
    STDERR,
    "Fournissez le mot de passe sur l’entrée standard (sans le placer en argument).\n",
);
$password = rtrim(stream_get_contents(STDIN), "\r\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "12 caractères minimum.\n");
    exit(1);
}
echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;

<?php
if (
    PHP_SAPI !== "cli" ||
    getenv("ACTU_TEST_MODE") !== "1" ||
    !str_starts_with(getenv("ACTU_STORAGE") ?: "", "/tmp/actu-")
) {
    fwrite(
        STDERR,
        "Utilisez ACTU_TEST_MODE=1 et une base ACTU_STORAGE sous /tmp/actu-.\n",
    );
    exit(1);
}
require __DIR__ . "/../app/bootstrap.php";
$id = (int) run("SELECT id FROM sources LIMIT 1")->fetchColumn();
foreach (["rss.xml", "atom.xml"] as $file) {
    foreach (
        parse_feed(file_get_contents(__DIR__ . "/fixtures/" . $file))
        as $article
    ) {
        save_article($id, $article);
    };
}
echo "Deux articles de test préparés.\n";

<?php
declare(strict_types=1);
$dir = sys_get_temp_dir() . "/actu-collector-" . bin2hex(random_bytes(6));
putenv("ACTU_STORAGE=" . $dir);
require __DIR__ . "/../app/bootstrap.php";
$port = 19000 + random_int(1, 10000);
$command = [PHP_BINARY, "-S", "127.0.0.1:" . $port, __DIR__ . "/proxy.php"];
$process = proc_open(
    $command,
    [
        0 => ["pipe", "r"],
        1 => ["file", $dir . "-server.log", "a"],
        2 => ["file", $dir . "-server.log", "a"],
    ],
    $pipes,
    ROOT,
);
if (!is_resource($process)) {
    throw new RuntimeException("Serveur fixture indisponible");
}
$count = 0;
function check(bool $v, string $label): void
{
    global $count;
    if (!$v) {
        throw new RuntimeException($label);
    }
    $count++;
    echo "OK " . $label . PHP_EOL;
}
try {
    for ($i = 0; $i < 30; $i++) {
        $s = @fsockopen("127.0.0.1", $port, $err, $message, 0.1);
        if ($s) {
            fclose($s);
            break;
        }
        usleep(100000);
    }
    putenv("http_proxy=http://127.0.0.1:" . $port);
    putenv("https_proxy=http://127.0.0.1:" . $port);
    putenv("HTTPS_PROXY=http://127.0.0.1:" . $port);
    putenv("NO_PROXY=");
    putenv("no_proxy=");
    putenv("ACTU_PROXY_HOSTS=fixture.invalid");
    db();
    run("INSERT INTO sources(name,url,category) VALUES(?,?,?)", [
        "Fixture",
        "http://fixture.invalid/rss",
        "cyber",
    ]);
    $id = (int) db()->lastInsertId();
    $source = run("SELECT * FROM sources WHERE id=?", [$id])->fetch();
    check(
        collect_source($source, true) === 1,
        "Collecte HTTP réelle via proxy local de test",
    );
    $source = run("SELECT * FROM sources WHERE id=?", [$id])->fetch();
    check(
        $source["success_at"] !== null && $source["error"] === null,
        "Diagnostic du succès",
    );
    check($source["etag"] === '"fixture-v1"', "ETag conservé");
    check(collect_source($source, true) === 0, "HTTP 304 sans nouvel import");
    check(
        collect_source($source, false) === 0,
        "Respect du délai de fraîcheur",
    );
    check(
        fetch_url("http://fixture.invalid/redirect")["status"] === 200,
        "Redirection relative publique",
    );
    $rejected = false;
    try {
        fetch_url("http://fixture.invalid/private");
    } catch (Throwable $e) {
        $rejected = true;
    }
    check($rejected, "Redirection vers adresse privée refusée");
    $large = false;
    try {
        fetch_url("http://fixture.invalid/large");
    } catch (Throwable $e) {
        $large = true;
    }
    check($large, "Réponse supérieure à 8 Mo refusée");
    run("UPDATE sources SET url=?,etag=NULL,modified=NULL WHERE id=?", [
        "http://fixture.invalid/fail",
        $id,
    ]);
    $source = run("SELECT * FROM sources WHERE id=?", [$id])->fetch();
    check(collect_source($source, true) === -1, "Erreur HTTP signalée");
    check(
        (int) run("SELECT COUNT(*) FROM articles WHERE source_id=?", [
            $id,
        ])->fetchColumn() === 1,
        "Articles préservés après échec",
    );
    run("UPDATE sources SET url=? WHERE id=?", [
        "http://fixture.invalid/broken",
        $id,
    ]);
    $source = run("SELECT * FROM sources WHERE id=?", [$id])->fetch();
    check(collect_source($source, true) === -1, "Erreur XML signalée");
    check(
        (int) run("SELECT COUNT(*) FROM articles WHERE source_id=?", [
            $id,
        ])->fetchColumn() === 1,
        "Transaction préservée après XML invalide",
    );
    echo "PASS : $count vérifications de collecte HTTP.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($dir . "/*") ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
    @unlink($dir . "-server.log");
}

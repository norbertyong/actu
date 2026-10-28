<?php
declare(strict_types=1);
require __DIR__ . "/../app/bootstrap.php";
if (PHP_SAPI !== "cli") {
    exit();
}
$options = getopt("", ["force", "source:", "limit:"]);
db();
$lock = fopen(store_dir() . "/collect.lock", "c");
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Collecte déjà en cours.\n");
    exit(2);
}
$sql = "SELECT * FROM sources WHERE enabled=1";
$args = [];
if (isset($options["source"])) {
    $sql .= " AND id=?";
    $args[] = (int) $options["source"];
}
$sql .= " ORDER BY COALESCE(checked_at,0),id";
if (isset($options["limit"])) {
    $sql .= " LIMIT " . max(1, (int) $options["limit"]);
}
$errors = 0;
$sources = run($sql, $args)->fetchAll();
foreach ($sources as $s) {
    $n = collect_source($s, isset($options["force"]));
    echo $s["name"] .
        ": " .
        ($n < 0 ? "échec (voir Sources)" : $n . " entrées traitées") .
        PHP_EOL;
    if ($n < 0) {
        $errors++;
    }
}
$notifications = generate_notifications();
echo $notifications . " nouvelles notifications.\n";
run("DELETE FROM visits WHERE day < ?", [date("Y-m-d", time() - 90 * 86400)]);
flock($lock, LOCK_UN);
fclose($lock);
exit($errors ? 1 : 0);

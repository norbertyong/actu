<?php
declare(strict_types=1);
require __DIR__ . "/../app/bootstrap.php";
if (PHP_SAPI !== "cli") {
    exit();
}
$options = getopt("", ["limit:"]);
$limit = min(20, max(1, (int) ($options["limit"] ?? 5)));
db();
$lock = fopen(store_dir() . "/enrich.lock", "c");
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Enrichissement déjà en cours.\n");
    exit(2);
}
$ids = run(
    "SELECT id FROM cves WHERE cvss IS NULL ORDER BY id DESC LIMIT " . $limit,
)->fetchAll(PDO::FETCH_COLUMN);
$failed = 0;
foreach ($ids as $i => $id) {
    if ($i > 0) {
        sleep(7);
    } // Respect the public NVD unauthenticated request window.
    try {
        $r = fetch_url(
            "https://services.nvd.nist.gov/rest/json/cves/2.0?cveId=" .
                rawurlencode($id),
        );
        $score = import_cvss($id, $r["body"]);
        echo $id .
            ": " .
            ($score === null ? "score non disponible" : "CVSS " . $score) .
            PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, $id . " : " . $e->getMessage() . PHP_EOL);
        $failed++;
    }
}
exit($failed ? 1 : 0);

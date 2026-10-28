<?php
declare(strict_types=1);
require __DIR__ . "/../app/bootstrap.php";
if (PHP_SAPI !== "cli") {
    exit();
}
$options = getopt("", ["send"]);
db();
$lock = fopen(store_dir() . "/notify.lock", "c");
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Envoi déjà en cours.\n");
    exit(2);
}
$webhook = getenv("ACTU_ALERT_WEBHOOK") ?: "";
$email = getenv("ACTU_ALERT_EMAIL") ?: "";
$from = getenv("ACTU_MAIL_FROM") ?: "";
if (
    $email &&
    (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !filter_var($from, FILTER_VALIDATE_EMAIL))
) {
    fwrite(
        STDERR,
        "ACTU_ALERT_EMAIL et ACTU_MAIL_FROM doivent être des adresses email valides.\n",
    );
    exit(1);
}
$channels = [];
if ($webhook) {
    $channels[] = "webhook";
}
if ($email) {
    $channels[] = "email";
}
$rows = run(
    "SELECT n.*,r.name,a.title,a.url FROM notifications n JOIN rules r ON r.id=n.rule_id JOIN articles a ON a.id=n.article_id WHERE n.delivered=0 AND r.enabled=1 ORDER BY n.id LIMIT 100",
)->fetchAll();
if (!isset($options["send"])) {
    echo count($rows) .
        " notifications en attente ; canaux : " .
        (implode(", ", $channels) ?: "aucun") .
        ". Mode aperçu : aucun envoi.\n";
    exit(0);
}
if (!$channels) {
    fwrite(
        STDERR,
        "Aucun canal configuré. Notifications conservées sur le site.\n",
    );
    exit(1);
}
$errors = 0;
foreach ($rows as $n) {
    foreach ($channels as $channel) {
        if (
            run(
                "SELECT 1 FROM deliveries WHERE notification_id=? AND channel=?",
                [$n["id"], $channel],
            )->fetchColumn()
        ) {
            continue;
        }
        try {
            if ($channel === "webhook") {
                if (parse_url($webhook, PHP_URL_SCHEME) !== "https") {
                    throw new InvalidArgumentException("Webhook HTTPS requis.");
                }
                fetch_url(
                    $webhook,
                    [
                        "Content-Type: application/json",
                        "Idempotency-Key: actu-" . $n["id"],
                    ],
                    json_encode(
                        [
                            "id" => $n["id"],
                            "rule" => $n["name"],
                            "title" => $n["title"],
                            "url" => $n["url"],
                        ],
                        JSON_THROW_ON_ERROR,
                    ),
                );
            } else {
                $body = $n["name"] . "\n" . $n["title"] . "\n" . $n["url"];
                if (
                    !mail($email, "Actu Cyber & IT — nouvelle alerte", $body, [
                        "From" => $from,
                        "Content-Type" => "text/plain; charset=UTF-8",
                    ])
                ) {
                    throw new RuntimeException(
                        "Le serveur mail a refusé le message.",
                    );
                }
            }
            run("INSERT OR IGNORE INTO deliveries VALUES(?,?,?)", [
                $n["id"],
                $channel,
                time(),
            ]);
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                "Notification " .
                    $n["id"] .
                    " / " .
                    $channel .
                    " : échec, vérifier le canal et sa connectivité." .
                    PHP_EOL,
            );
            $errors++;
        }
    }
    $done = true;
    foreach ($channels as $channel) {
        if (
            !run(
                "SELECT 1 FROM deliveries WHERE notification_id=? AND channel=?",
                [$n["id"], $channel],
            )->fetchColumn()
        ) {
            $done = false;
        }
    }
    if ($done) {
        run("UPDATE notifications SET delivered=1 WHERE id=?", [$n["id"]]);
    }
}
echo count($rows) . " notifications examinées.\n";
exit($errors ? 1 : 0);

<?php
declare(strict_types=1);
$dir = sys_get_temp_dir() . "/actu-test-" . bin2hex(random_bytes(8));
putenv("ACTU_STORAGE=" . $dir);
require __DIR__ . "/../app/bootstrap.php";
$count = 0;
function check(bool $condition, string $label): void
{
    global $count;
    if (!$condition) {
        throw new RuntimeException("ÉCHEC : " . $label);
    }
    $count++;
    echo "OK " . $label . PHP_EOL;
}
function rejects(callable $f, string $label): void
{
    $rejected = false;
    try {
        $f();
    } catch (Throwable $e) {
        $rejected = true;
    }
    check($rejected, $label);
}
try {
    $sources = run("SELECT COUNT(*) FROM sources")->fetchColumn();
    check((int) $sources === 13, "Initialisation et sources uniques");
    db();
    check(
        (int) run("SELECT COUNT(*) FROM sources")->fetchColumn() === 13,
        "Initialisation répétable",
    );
    $rss = parse_feed(file_get_contents(__DIR__ . "/fixtures/rss.xml"));
    check(count($rss) === 1, "RSS : lien dangereux exclu");
    check(
        $rss[0]["title"] === "Linux & CVE-2026-12345",
        "RSS : entités décodées",
    );
    check(!str_contains($rss[0]["summary"], "<p>"), "Résumé nettoyé");
    $atom = parse_feed(file_get_contents(__DIR__ . "/fixtures/atom.xml"));
    check(
        count($atom) === 1 && $atom[0]["author"] === "Équipe veille",
        "Atom avec espace de noms",
    );
    rejects(fn() => parse_feed("<broken>"), "XML invalide refusé");
    rejects(
        fn() => parse_feed(
            '<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss/>',
        ),
        "Entités XML refusées",
    );
    foreach (
        [
            "http://127.0.0.1/x",
            "http://10.0.0.1",
            "http://[::1]/",
            "http://[::ffff:127.0.0.1]/",
            "file:///tmp/a",
            "https://user:pass@example.org/",
            "http://localhost/",
            "http://100.64.0.1/",
            "http://224.0.0.1/",
            "http://[ff02::1]/",
            "http://192.0.2.1/",
        ]
        as $url
    ) {
        rejects(
            fn() => public_host($url),
            "Destination interne/invalide refusée",
        );
    }
    check(
        safe_link("javascript:alert(1)") === "" &&
            safe_link("https://example.org/") !== "",
        "Validation des protocoles de liens",
    );
    $sid = (int) run("SELECT id FROM sources LIMIT 1")->fetchColumn();
    $id = save_article($sid, $rss[0]);
    save_article($sid, $rss[0]);
    check(
        (int) run("SELECT COUNT(*) FROM articles")->fetchColumn() === 1,
        "Déduplication persistante par URL",
    );
    check(count(article_cves($id)) === 1, "Extraction de CVE");
    $f = [
        "q" => "Linux",
        "cat" => "",
        "src" => (string) $sid,
        "period" => "",
        "unread" => "",
        "kev" => "",
    ];
    [$from, $params] = article_query($f, "reader");
    check(
        (int) run("SELECT COUNT(*)" . $from, $params)->fetchColumn() === 1,
        "Recherche combinée avec source",
    );
    $f["q"] = "%";
    [$from, $params] = article_query($f, "reader");
    check(
        (int) run("SELECT COUNT(*)" . $from, $params)->fetchColumn() === 0,
        "Recherche : % traité littéralement",
    );
    $f["q"] = "";
    run("INSERT INTO states VALUES(?,?,1,1)", ["reader", $id]);
    [$from, $params] = article_query($f, "reader", true);
    check(
        (int) run("SELECT COUNT(*)" . $from, $params)->fetchColumn() === 1,
        "Favori retrouvé",
    );
    [$from, $params] = article_query($f, "other", true);
    check(
        (int) run("SELECT COUNT(*)" . $from, $params)->fetchColumn() === 0,
        "Favoris isolés par navigateur",
    );
    $f["unread"] = "1";
    [$from, $params] = article_query($f, "reader");
    check(
        (int) run("SELECT COUNT(*)" . $from, $params)->fetchColumn() === 0,
        "Articles lus exclus",
    );
    $kev = json_encode([
        "vulnerabilities" => [
            [
                "cveID" => "CVE-2026-12345",
                "vendorProject" => "Vendor",
                "product" => "Linux",
                "vulnerabilityName" => "Test Linux",
                "shortDescription" => "Faille exploitée",
                "dateAdded" => "2026-10-28",
                "requiredAction" => "Appliquer le correctif",
                "dueDate" => "2026-11-01",
            ],
        ],
    ]);
    check(import_kev($sid, $kev) === 1, "Import CISA KEV");
    check(
        (int) article_cves($id)[0]["exploited"] === 1 &&
            article_cves($id)[0]["cvss"] === null,
        "Exploitation connue sans score CVSS inventé",
    );
    $score = import_cvss(
        "CVE-2026-12345",
        json_encode([
            "vulnerabilities" => [
                [
                    "cve" => [
                        "id" => "CVE-2026-12345",
                        "metrics" => [
                            "cvssMetricV31" => [
                                ["cvssData" => ["baseScore" => 9.8]],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    );
    check($score === 9.8, "Enrichissement CVSS NVD");
    check(
        import_cvss("CVE-2026-12345", '{"vulnerabilities":[]}') === null,
        "Absence de score NVD explicite",
    );
    $extra = save_article($sid, [
        "title" => "Équipe CVE-2026-99999",
        "url" => "https://example.org/unicode",
        "summary" => "Test",
        "author" => "",
        "published" => 1,
    ]);
    $f["q"] = "éQUIPE";
    $f["unread"] = "";
    [$from, $params] = article_query($f, "reader");
    check(
        (int) run("SELECT COUNT(*)" . $from, $params)->fetchColumn() === 1,
        "Recherche Unicode sans distinction de casse",
    );
    run("UPDATE cves SET cvss=10 WHERE id=?", ["CVE-2026-99999"]);
    $f["q"] = "";
    $f["sort"] = "severity";
    [$from, $params] = article_query($f, "reader");
    check(
        (int) run(
            "SELECT a.id" .
                $from .
                " ORDER BY " .
                article_order($f) .
                " LIMIT 1",
            $params,
        )->fetchColumn() === $extra,
        "Classement CVSS prioritaire sur la date",
    );
    run("DELETE FROM articles WHERE id=?", [$extra]);
    run("INSERT INTO rules(name,keyword) VALUES(?,?)", ["Linux", "Linux"]);
    $n = generate_notifications();
    check($n === 2, "Correspondances d’alertes");
    check(generate_notifications() === 0, "Notifications sans doublon");
    $opml = opml_export();
    check(str_contains($opml, "xmlUrl="), "Export OPML");
    check(opml_import($opml) === 0, "Réimport OPML sans doublon");
    $n = opml_import(
        '<opml><body><outline text="Test" xmlUrl="https://example.org/rss"/></body></opml>',
    );
    check(
        $n === 1 &&
            (int) run("SELECT enabled FROM sources WHERE url=?", [
                "https://example.org/rss",
            ])->fetchColumn() === 0,
        "Source OPML désactivée par défaut",
    );
    rejects(
        fn() => opml_import("<!DOCTYPE opml><opml/>"),
        "OPML avec DOCTYPE refusé",
    );
    putenv("ACTU_ADMIN_USER=test");
    putenv("ACTU_ADMIN_PASSWORD=correct-test-password");
    $_SERVER["REMOTE_ADDR"] = "192.0.2.1";
    for ($i = 0; $i < 5; $i++) {
        check(!login("test", "wrong"), "Mauvais mot de passe refusé");
    }
    check(!login("test", "correct-test-password"), "Limitation des tentatives");
    run("DELETE FROM attempts");
    check(
        login("test", "correct-test-password") && is_admin(),
        "Connexion et session",
    );
    $_SESSION["admin_time"] = time() - 1801;
    check(!is_admin(), "Expiration de session");
    putenv(
        "ACTU_ADMIN_PASSWORD_HASH=" .
            password_hash("hash-test-password", PASSWORD_DEFAULT),
    );
    check(
        login("test", "hash-test-password"),
        "Authentification par empreinte",
    );
    putenv("ACTU_ADMIN_USER");
    check(!login("", ""), "Configuration vide refusée");
    $_GET = ["q" => ["invalid"], "src" => ["invalid"]];
    check(
        filters()["q"] === "" && filters()["src"] === "",
        "Paramètres tableaux neutralisés",
    );
    check(e("<script>") === "&lt;script&gt;", "Échappement HTML");
    visit("news");
    visit("news");
    check(
        (int) run("SELECT count FROM visits")->fetchColumn() === 2,
        "Compteurs agrégés",
    );
    echo "PASS : $count vérifications.\n";
} finally {
    foreach (glob($dir . "/*") ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
}

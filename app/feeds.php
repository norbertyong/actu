<?php
declare(strict_types=1);
function safe_link(string $url): string
{
    $p = parse_url(trim($url));
    if (
        !$p ||
        !in_array(strtolower($p["scheme"] ?? ""), ["http", "https"], true) ||
        empty($p["host"]) ||
        isset($p["user"]) ||
        isset($p["pass"])
    ) {
        return "";
    }
    return trim($url);
}
function ip_in_range(string $ip, string $range): bool
{
    [$network, $bits] = explode("/", $range);
    $address = inet_pton($ip);
    $base = inet_pton($network);
    if (
        $address === false ||
        $base === false ||
        strlen($address) !== strlen($base)
    ) {
        return false;
    }
    $full = intdiv((int) $bits, 8);
    $remaining = (int) $bits % 8;
    return substr($address, 0, $full) === substr($base, 0, $full) &&
        (!$remaining ||
            (ord($address[$full]) & (255 << (8 - $remaining))) ===
                (ord($base[$full]) & (255 << (8 - $remaining))));
}
function public_ip(string $ip): bool
{
    if (
        !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        )
    ) {
        return false;
    }
    $blocked = [
        "0.0.0.0/8",
        "100.64.0.0/10",
        "192.0.0.0/24",
        "192.0.2.0/24",
        "198.18.0.0/15",
        "198.51.100.0/24",
        "203.0.113.0/24",
        "224.0.0.0/4",
        "240.0.0.0/4",
    ];
    if (str_contains($ip, ":")) {
        if (!ip_in_range($ip, "2000::/3")) {
            return false;
        }
        $blocked = ["2001::/32", "2001:db8::/32", "2002::/16", "3fff::/20"];
    }
    foreach ($blocked as $range) {
        if (ip_in_range($ip, $range)) {
            return false;
        }
    }
    return true;
}
function public_host(string $url): array
{
    if (!safe_link($url)) {
        throw new InvalidArgumentException(
            "URL HTTP/HTTPS requise, sans identifiants.",
        );
    }
    $p = parse_url($url);
    $host = strtolower($p["host"]);
    if (
        !in_array(
            $p["port"] ?? ($p["scheme"] === "https" ? 443 : 80),
            [80, 443],
            true,
        )
    ) {
        throw new InvalidArgumentException("Ports 80 et 443 uniquement.");
    }
    $ipHost = trim($host, "[]");
    if (filter_var($ipHost, FILTER_VALIDATE_IP)) {
        $ips = [$ipHost];
    } else {
        if (
            !str_contains($host, ".") ||
            str_ends_with($host, ".local") ||
            str_ends_with($host, ".internal") ||
            str_ends_with($host, ".localhost")
        ) {
            throw new InvalidArgumentException("Hôte public requis.");
        }
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            if (isset($r["ip"]) || isset($r["ipv6"])) {
                $ips[] = $r["ip"] ?? $r["ipv6"];
            }
        }
    }
    if (!$ips) {
        // Only explicitly trusted destinations may rely on a managed proxy's DNS.
        $trusted = array_filter(
            array_map("trim", explode(",", getenv("ACTU_PROXY_HOSTS") ?: "")),
        );
        if (
            !filter_var($ipHost, FILTER_VALIDATE_IP) &&
            in_array($host, $trusted, true) &&
            (getenv("HTTPS_PROXY") || getenv("https_proxy"))
        ) {
            return [$host, []];
        }
        throw new RuntimeException(
            "Résolution DNS impossible. Avec un proxy DNS distant, configurer ACTU_PROXY_HOSTS pour les hôtes de confiance.",
        );
    }
    foreach ($ips as $ip) {
        if (!public_ip($ip)) {
            throw new InvalidArgumentException(
                "Adresse privée ou réservée interdite.",
            );
        }
    }
    return [$host, $ips];
}
function fetch_url(
    string $url,
    array $headers = [],
    ?string $post = null,
): array {
    for ($hop = 0; $hop < 4; $hop++) {
        [$host, $ips] = public_host($url);
        $p = parse_url($url);
        $port = $p["port"] ?? ($p["scheme"] === "https" ? 443 : 80);
        $body = "";
        $response = [];
        $tooLarge = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => "ActuCyberIT/0.2.0",
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RESOLVE => $ips
                ? [
                    $host .
                    ":" .
                    $port .
                    ":" .
                    (str_contains($ips[0], ":")
                        ? "[" . $ips[0] . "]"
                        : $ips[0]),
                ]
                : [],
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (
                &$body,
                &$tooLarge,
            ) {
                if (strlen($body) + strlen($chunk) > 8 * 1024 * 1024) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$response) {
                if (str_contains($line, ":")) {
                    [$k, $v] = explode(":", $line, 2);
                    $response[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($post !== null) {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
            ]);
        }
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_errno($ch);
        curl_close($ch);
        if ($ok === false) {
            throw new RuntimeException(
                $tooLarge
                    ? "Réponse trop volumineuse."
                    : "Échec réseau (cURL " . $err . ").",
            );
        }
        if (
            in_array($code, [301, 302, 303, 307, 308], true) &&
            isset($response["location"])
        ) {
            if ($post !== null) {
                throw new RuntimeException("Redirection du webhook refusée.");
            }
            $next = $response["location"];
            if (str_starts_with($next, "//")) {
                $next = $p["scheme"] . ":" . $next;
            } elseif (str_starts_with($next, "/")) {
                $next =
                    $p["scheme"] .
                    "://" .
                    $p["host"] .
                    (isset($p["port"]) ? ":" . $p["port"] : "") .
                    $next;
            } elseif (!parse_url($next, PHP_URL_SCHEME)) {
                $next =
                    $p["scheme"] .
                    "://" .
                    $p["host"] .
                    (isset($p["port"]) ? ":" . $p["port"] : "") .
                    preg_replace('~/[^/]*$~', "/", $p["path"] ?? "/") .
                    $next;
            }
            if (
                $p["scheme"] === "https" &&
                parse_url($next, PHP_URL_SCHEME) !== "https"
            ) {
                throw new RuntimeException(
                    "Redirection HTTPS vers HTTP refusée.",
                );
            }
            $url = $next;
            continue;
        }
        if ($code !== 304 && ($code < 200 || $code >= 300)) {
            throw new RuntimeException("Réponse HTTP " . $code . ".");
        }
        return ["body" => $body, "status" => $code, "headers" => $response];
    }
    throw new RuntimeException("Trop de redirections.");
}
function parse_feed(string $xml): array
{
    if (preg_match("/<!DOCTYPE|<!ENTITY/i", $xml)) {
        throw new InvalidArgumentException(
            "Déclarations XML externes interdites.",
        );
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $sx = simplexml_load_string(
            $xml,
            SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOCDATA,
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$sx) {
        throw new InvalidArgumentException("Flux XML invalide.");
    }
    $items = [];
    $isAtom = $sx->getName() === "feed";
    if ($isAtom) {
        $ns = $sx->getNamespaces(true);
        $root = isset($ns[""]) ? $sx->children($ns[""]) : $sx;
        $entries = $root->entry;
    } elseif (isset($sx->channel)) {
        $entries = $sx->channel->item;
    } else {
        throw new InvalidArgumentException("Format RSS/Atom non reconnu.");
    }
    foreach ($entries as $it) {
        $link = (string) $it->link;
        if ($isAtom) {
            foreach ($it->link as $l) {
                $attrs = $l->attributes();
                if (
                    (string) $attrs["rel"] === "alternate" ||
                    (string) $attrs["rel"] === ""
                ) {
                    $link = (string) $attrs["href"];
                    break;
                }
            };
        }
        $link = safe_link($link);
        $title = trim(strip_tags((string) $it->title));
        if ($link === "" || $title === "") {
            continue;
        }
        $date = strtotime(
            (string) ($it->pubDate ?? ($it->published ?? ($it->updated ?? ""))),
        );
        $dc = $it->children("http://purl.org/dc/elements/1.1/");
        $items[] = [
            "title" => mb_substr($title, 0, 500),
            "url" => $link,
            "summary" => mb_substr(
                trim(
                    html_entity_decode(
                        strip_tags(
                            (string) ($it->description ??
                                ($it->summary ?? ($it->content ?? ""))),
                        ),
                        ENT_QUOTES | ENT_HTML5,
                        "UTF-8",
                    ),
                ),
                0,
                2000,
            ),
            "author" => mb_substr(
                (string) ($isAtom
                    ? $it->author->name
                    : $it->author ?? $dc->creator),
                0,
                200,
            ),
            "published" => $date !== false ? $date : time(),
        ];
        if (count($items) >= 500) {
            break;
        }
    }
    return $items;
}
function save_article(int $source, array $item): int
{
    $url = safe_link($item["url"]);
    if ($url === "") {
        throw new InvalidArgumentException("Lien article invalide.");
    }
    $url = preg_replace('/#.*$/', "", $url);
    $guid = hash("sha256", $url);
    $previous = run('SELECT id,title,summary FROM articles WHERE guid=?', [$guid])->fetch();
    $previousCves = $previous ? run('SELECT cve_id FROM article_cves WHERE article_id=?', [$previous['id']])->fetchAll(PDO::FETCH_COLUMN) : [];
    run(
        "INSERT INTO articles(guid,source_id,title,url,summary,author,published,created) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(guid) DO UPDATE SET title=excluded.title,summary=excluded.summary,author=excluded.author",
        [
            $guid,
            $source,
            $item["title"],
            $url,
            $item["summary"],
            $item["author"],
            $item["published"],
            time(),
        ],
    );
    $id = (int) run("SELECT id FROM articles WHERE guid=?", [
        $guid,
    ])->fetchColumn();
    preg_match_all(
        "/\bCVE-\d{4}-\d{4,}\b/i",
        $item["title"] . " " . $item["summary"],
        $matches,
    );
    run('DELETE FROM article_cves WHERE article_id=?', [$id]);
    foreach (array_unique($matches[0]) as $cve) {
        $cve = strtoupper($cve);
        run("INSERT OR IGNORE INTO cves(id) VALUES(?)", [$cve]);
        run("INSERT OR IGNORE INTO article_cves VALUES(?,?)", [$id, $cve]);
    }
    if (!$previous || $previous['title'] !== $item['title'] || $previous['summary'] !== $item['summary']) {
        foreach (array_unique([...$previousCves, ...array_map('strtoupper', $matches[0])]) as $cve) tracking_signal($cve);
    }
    return $id;
}
function import_kev(int $source, string $json): int
{
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (
        !isset($data["vulnerabilities"]) ||
        !is_array($data["vulnerabilities"])
    ) {
        throw new InvalidArgumentException("Catalogue KEV invalide.");
    }
    $n = 0;
    foreach ($data["vulnerabilities"] as $v) {
        $id = strtoupper((string) ($v["cveID"] ?? ""));
        if (!preg_match('/^CVE-\d{4}-\d{4,}$/D', $id)) {
            continue;
        }
        $previousCve = run('SELECT * FROM cves WHERE id=?', [$id])->fetch();
        run(
            "INSERT INTO cves(id,vendor,product,exploited,action,due_date) VALUES(?,?,?,1,?,?) ON CONFLICT(id) DO UPDATE SET vendor=excluded.vendor,product=excluded.product,exploited=1,action=excluded.action,due_date=excluded.due_date",
            [
                $id,
                (string) ($v["vendorProject"] ?? ""),
                (string) ($v["product"] ?? ""),
                (string) ($v["requiredAction"] ?? ""),
                (string) ($v["dueDate"] ?? ""),
            ],
        );
        tracking_metadata_changed($id, $previousCve);
        save_article($source, [
            "title" =>
                $id .
                " — " .
                ($v["vulnerabilityName"] ?? "Vulnérabilité exploitée"),
            "url" => "https://nvd.nist.gov/vuln/detail/" . $id,
            "summary" =>
                trim(
                    ($v["vendorProject"] ?? "") .
                        " " .
                        ($v["product"] ?? "") .
                        " — " .
                        ($v["shortDescription"] ?? ""),
                ) .
                " Action : " .
                ($v["requiredAction"] ?? ""),
            "author" => "CISA",
            "published" =>
                strtotime((string) ($v["dateAdded"] ?? "")) ?: time(),
        ]);
        $n++;
    }
    return $n;
}
function collect_source(array $source, bool $force = false): int
{
    if (
        !$force &&
        $source["checked_at"] &&
        (int) $source["checked_at"] > time() - 1200
    ) {
        return 0;
    }
    try {
        $headers = [
            "Accept: application/rss+xml, application/atom+xml, application/json, application/xml",
        ];
        foreach (
            ["etag" => "If-None-Match", "modified" => "If-Modified-Since"]
            as $key => $name
        ) {
            if (!empty($source[$key])) {
                $headers[] =
                    $name . ": " . str_replace(["\r", "\n"], "", $source[$key]);
            }
        }
        $r = fetch_url($source["url"], $headers);
        $count = 0;
        if ($r["status"] !== 304) {
            db()->beginTransaction();
            try {
                if ($source["kind"] === "kev") {
                    $count = import_kev((int) $source["id"], $r["body"]);
                } else {
                    foreach (parse_feed($r["body"]) as $item) {
                        save_article((int) $source["id"], $item);
                        $count++;
                    };
                }
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                throw $e;
            }
        }
        run(
            "UPDATE sources SET checked_at=?,success_at=?,error=NULL,etag=?,modified=? WHERE id=?",
            [
                time(),
                time(),
                $r["headers"]["etag"] ?? $source["etag"],
                $r["headers"]["last-modified"] ?? $source["modified"],
                $source["id"],
            ],
        );
        return $count;
    } catch (Throwable $e) {
        run("UPDATE sources SET checked_at=?,error=? WHERE id=?", [
            time(),
            mb_substr($e->getMessage(), 0, 200),
            $source["id"],
        ]);
        return -1;
    }
}
function generate_notifications(): int
{
    $count = 0;
    foreach (run("SELECT * FROM rules WHERE enabled=1")->fetchAll() as $rule) {
        foreach (
            run(
                "SELECT a.id,a.title,a.summary,s.category FROM articles a JOIN sources s ON s.id=a.source_id WHERE a.created>=?",
                [time() - 7 * 86400],
            )->fetchAll()
            as $a
        ) {
            if (
                ($rule["category"] === "" ||
                    $rule["category"] === $a["category"]) &&
                mb_stripos(
                    $a["title"] . " " . $a["summary"],
                    $rule["keyword"],
                ) !== false
            ) {
                $stmt = run(
                    "INSERT OR IGNORE INTO notifications(rule_id,article_id,created) VALUES(?,?,?)",
                    [$rule["id"], $a["id"], time()],
                );
                $count += $stmt->rowCount();
            }
        }
    }
    return $count;
}
function opml_export(): string
{
    $xml = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0"><head><title>Actu Cyber &amp; IT</title></head><body/></opml>',
    );
    foreach (
        run("SELECT * FROM sources WHERE kind='rss' ORDER BY name")->fetchAll()
        as $s
    ) {
        $o = $xml->body->addChild("outline");
        foreach (
            [
                "text" => $s["name"],
                "title" => $s["name"],
                "type" => "rss",
                "xmlUrl" => $s["url"],
                "category" => $s["category"],
            ]
            as $k => $v
        ) {
            $o->addAttribute($k, $v);
        }
    }
    return $xml->asXML();
}
function opml_import(string $text): int
{
    if (
        strlen($text) > 1024 * 1024 ||
        preg_match("/<!DOCTYPE|<!ENTITY/i", $text)
    ) {
        throw new InvalidArgumentException("OPML trop volumineux ou interdit.");
    }
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($text, SimpleXMLElement::class, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$xml || $xml->getName() !== "opml") {
        throw new InvalidArgumentException("OPML invalide.");
    }
    $nodes = $xml->xpath("//outline[@xmlUrl]");
    if (count($nodes) > 100) {
        throw new InvalidArgumentException("100 sources maximum par import.");
    }
    $sources = [];
    foreach ($nodes as $o) {
        $url = (string) $o["xmlUrl"];
        if (!safe_link($url)) {
            throw new InvalidArgumentException("URL OPML invalide.");
        }
        $cat = (string) $o["category"];
        $sources[] = [
            mb_substr((string) ($o["title"] ?? $o["text"]), 0, 150) ?:
            "Source importée",
            $url,
            in_array($cat, ["cyber", "it", "linux", "vulprod"], true)
                ? $cat
                : "cyber",
        ];
    }
    db()->beginTransaction();
    $count = 0;
    try {
        foreach ($sources as $s) {
            $count += run(
                "INSERT OR IGNORE INTO sources(name,url,category,enabled) VALUES(?,?,?,0)",
                $s,
            )->rowCount();
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    return $count;
}

function import_cvss(string $id, string $json): ?float
{
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    foreach ($data["vulnerabilities"] ?? [] as $entry) {
        $cve = $entry["cve"] ?? [];
        if (($cve["id"] ?? "") !== $id) {
            continue;
        }
        foreach (
            ["cvssMetricV40", "cvssMetricV31", "cvssMetricV30", "cvssMetricV2"]
            as $format
        ) {
            foreach ($cve["metrics"][$format] ?? [] as $metric) {
                $score = $metric["cvssData"]["baseScore"] ?? null;
                if (is_numeric($score) && $score >= 0 && $score <= 10) {
                    $previousCve = run('SELECT * FROM cves WHERE id=?', [$id])->fetch();
                    run("UPDATE cves SET cvss=? WHERE id=?", [
                        (float) $score,
                        $id,
                    ]);
                    tracking_metadata_changed($id, $previousCve);
                    return (float) $score;
                }
            }
        }
    }
    return null;
}

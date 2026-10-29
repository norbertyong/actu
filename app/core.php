<?php
declare(strict_types=1);
function is_https(): bool
{
    return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        getenv("ACTU_HTTPS") === "1";
}
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}
function input(string $name, string $default = "", ?array $from = null): string
{
    $v = ($from ?? $_GET)[$name] ?? $default;
    return is_string($v) ? trim($v) : $default;
}
function store_dir(): string
{
    return getenv("ACTU_STORAGE") ?: ROOT . "/storage";
}
function db(): PDO
{
    static $db;
    if ($db) {
        return $db;
    }
    $dir = store_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Stockage inaccessible");
    }
    $db = new PDO("sqlite:" . $dir . "/actu.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->sqliteCreateFunction(
        "actu_lower",
        fn($value) => mb_strtolower((string) $value),
        1,
        PDO::SQLITE_DETERMINISTIC,
    );
    $db->exec(
        "PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON",
    );
    $db->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS sources (id INTEGER PRIMARY KEY, name TEXT NOT NULL, url TEXT UNIQUE NOT NULL, category TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'rss', enabled INTEGER NOT NULL DEFAULT 1, checked_at INTEGER, success_at INTEGER, error TEXT, etag TEXT, modified TEXT);
        CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY, guid TEXT UNIQUE NOT NULL, source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE, title TEXT NOT NULL, url TEXT NOT NULL, summary TEXT NOT NULL, author TEXT NOT NULL, published INTEGER NOT NULL, created INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS articles_date ON articles(published DESC);
        CREATE TABLE IF NOT EXISTS cves (id TEXT PRIMARY KEY, vendor TEXT NOT NULL DEFAULT '', product TEXT NOT NULL DEFAULT '', exploited INTEGER NOT NULL DEFAULT 0, cvss REAL, action TEXT NOT NULL DEFAULT '', due_date TEXT NOT NULL DEFAULT '');
        CREATE TABLE IF NOT EXISTS article_cves (article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE, cve_id TEXT NOT NULL REFERENCES cves(id), PRIMARY KEY(article_id,cve_id));
        CREATE TABLE IF NOT EXISTS states (reader TEXT NOT NULL, article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE, favorite INTEGER NOT NULL DEFAULT 0, is_read INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(reader,article_id));
        CREATE TABLE IF NOT EXISTS searches (id INTEGER PRIMARY KEY, reader TEXT NOT NULL, name TEXT NOT NULL, filters TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS attempts (bucket TEXT PRIMARY KEY, failures INTEGER NOT NULL, started INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS visits (day TEXT NOT NULL, page TEXT NOT NULL, count INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(day,page));
        CREATE TABLE IF NOT EXISTS rules (id INTEGER PRIMARY KEY, name TEXT NOT NULL, keyword TEXT NOT NULL, category TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1);
        CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY, rule_id INTEGER NOT NULL REFERENCES rules(id) ON DELETE CASCADE, article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE, created INTEGER NOT NULL, delivered INTEGER NOT NULL DEFAULT 0, UNIQUE(rule_id,article_id));
        CREATE TABLE IF NOT EXISTS deliveries (notification_id INTEGER NOT NULL REFERENCES notifications(id) ON DELETE CASCADE, channel TEXT NOT NULL, delivered INTEGER NOT NULL, PRIMARY KEY(notification_id,channel));
        CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS technologies (id INTEGER PRIMARY KEY, reader TEXT NOT NULL, name TEXT NOT NULL, UNIQUE(reader,name));
        CREATE TABLE IF NOT EXISTS ai_cache (article_id INTEGER PRIMARY KEY REFERENCES articles(id) ON DELETE CASCADE, fingerprint TEXT NOT NULL, model TEXT NOT NULL, result TEXT NOT NULL, created INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS ai_usage (day TEXT PRIMARY KEY, requests INTEGER NOT NULL DEFAULT 0, reserved_tokens INTEGER NOT NULL DEFAULT 0);
        CREATE INDEX IF NOT EXISTS article_cves_cve ON article_cves(cve_id,article_id);
        CREATE INDEX IF NOT EXISTS articles_source ON articles(source_id,published);
        SQL
        ,
    );
    if (
        !$db
            ->query("SELECT 1 FROM settings WHERE name='initialized'")
            ->fetchColumn()
    ) {
        $db->beginTransaction();
        $stmt = $db->prepare(
            "INSERT OR IGNORE INTO sources(name,url,category,kind) VALUES(?,?,?,?)",
        );
        foreach (require ROOT . "/app/seeds.php" as $s) {
            $stmt->execute($s);
        }
        $db->exec("INSERT OR IGNORE INTO settings VALUES('initialized','1')");
        $db->commit();
    }
    return $db;
}
function run(string $sql, array $params = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s;
}
function is_admin(): bool
{
    return isset($_SESSION["admin_time"]) &&
        time() - (int) $_SESSION["admin_time"] <= 1800;
}
function redirect(string $url): never
{
    header("Location: " . base_path() . $url, true, 303);
    exit();
}
function require_admin(): void
{
    if (!is_admin()) {
        redirect("/login.php");
    }
}
function csrf(): string
{
    return $_SESSION["csrf"] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}
function verify_csrf(): void
{
    if (!hash_equals(csrf(), input("csrf", "", $_POST))) {
        http_response_code(403);
        exit("Formulaire expiré. Rechargez la page.");
    }
}
function reader(): string
{
    static $reader;
    if ($reader) {
        return $reader;
    }
    $token = $_COOKIE["actu_reader"] ?? "";
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        $token = bin2hex(random_bytes(32));
        setcookie("actu_reader", $token, [
            "expires" => time() + 31536000,
            "path" => base_path() . "/",
            "secure" => is_https(),
            "httponly" => true,
            "samesite" => "Lax",
        ]);
    }
    return $reader = hash("sha256", $token);
}
function auth_bucket(): string
{
    return hash("sha256", $_SERVER["REMOTE_ADDR"] ?? "cli");
}
function login(string $user, string $pass): bool
{
    $bucket = auth_bucket();
    $now = time();
    run("DELETE FROM attempts WHERE started < ?", [$now - 900]);
    $attempt = run("SELECT * FROM attempts WHERE bucket=?", [$bucket])->fetch();
    if ($attempt && $attempt["failures"] >= 5) {
        return false;
    }
    $expected = getenv("ACTU_ADMIN_USER") ?: "";
    $hash = getenv("ACTU_ADMIN_PASSWORD_HASH") ?: "";
    $plain = getenv("ACTU_ADMIN_PASSWORD") ?: "";
    $valid =
        $expected !== "" &&
        $pass !== "" &&
        hash_equals($expected, $user) &&
        ($hash !== ""
            ? password_verify($pass, $hash)
            : $plain !== "" && hash_equals($plain, $pass));
    if (!$valid) {
        run(
            "INSERT INTO attempts VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET failures=failures+1",
            [$bucket, $now],
        );
        return false;
    }
    run("DELETE FROM attempts WHERE bucket=?", [$bucket]);
    if (PHP_SAPI !== "cli") {
        session_regenerate_id(true);
    }
    $_SESSION["admin_time"] = $now;
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
    return true;
}
function filters(): array
{
    $period = input("period");
    $period = in_array($period, ["1", "7", "30"], true) ? $period : "";
    return [
        "sort" => input("sort") === "severity" ? "severity" : "latest",
        "q" => mb_substr(input("q"), 0, 200),
        "cat" => input("cat"),
        "src" => ctype_digit(input("src")) ? input("src") : "",
        "period" => $period,
        "unread" => input("unread") === "1" ? "1" : "",
        "kev" => input("kev") === "1" ? "1" : "",
        "tracked" => input("tracked") === "1" ? "1" : "",
        "cve" => preg_match('/^CVE-\d{4}-\d{4,}$/D', strtoupper(input('cve'))) ? strtoupper(input('cve')) : '',
    ];
}
function article_query(
    array $f,
    string $reader,
    bool $favorites = false,
    bool $vuln = false,
): array {
    $where = ["1=1"];
    $params = [$reader];
    if (($f['tracked'] ?? '') === '1') {
        $where[] = technology_match_sql();
        $params[] = $reader;
    }
    if (($f['cve'] ?? '') !== '') {
        $where[] = 'EXISTS(SELECT 1 FROM article_cves ac WHERE ac.article_id=a.id AND ac.cve_id=?)';
        $params[] = $f['cve'];
    }
    if ($f["q"] !== "") {
        $where[] =
            "(actu_lower(a.title) LIKE ? ESCAPE '\\' OR actu_lower(a.summary) LIKE ? ESCAPE '\\')";
        $q =
            "%" .
            strtr(mb_strtolower($f["q"]), [
                "\\" => "\\\\",
                "%" => "\\%",
                "_" => "\\_",
            ]) .
            "%";
        $params[] = $q;
        $params[] = $q;
    }
    if ($f["cat"] !== "") {
        $where[] = "s.category=?";
        $params[] = $f["cat"];
    }
    if ($f["src"] !== "") {
        $where[] = "s.id=?";
        $params[] = $f["src"];
    }
    if ($f["period"] !== "") {
        $where[] = "a.published>=?";
        $params[] = time() - 86400 * (int) $f["period"];
    }
    if ($f["unread"] === "1") {
        $where[] = "COALESCE(st.is_read,0)=0";
    }
    if ($favorites) {
        $where[] = "st.favorite=1";
    }
    if ($vuln) {
        $where[] =
            "(s.category='vulprod' OR EXISTS(SELECT 1 FROM article_cves ac WHERE ac.article_id=a.id))";
    }
    if ($f["kev"] === "1") {
        $where[] =
            "EXISTS(SELECT 1 FROM article_cves ac JOIN cves c ON c.id=ac.cve_id WHERE ac.article_id=a.id AND c.exploited=1)";
    }
    $from =
        " FROM articles a JOIN sources s ON s.id=a.source_id LEFT JOIN states st ON st.article_id=a.id AND st.reader=? WHERE " .
        implode(" AND ", $where);
    return [$from, $params];
}
function article_cves(int $id): array
{
    return run(
        "SELECT c.* FROM cves c JOIN article_cves ac ON ac.cve_id=c.id WHERE ac.article_id=?",
        [$id],
    )->fetchAll();
}
function when(?int $ts): string
{
    return $ts ? date("d/m/Y H:i", $ts) : "Jamais";
}
function visit(string $page): void
{
    run(
        "INSERT INTO visits VALUES(?,?,1) ON CONFLICT(day,page) DO UPDATE SET count=count+1",
        [date("Y-m-d"), $page],
    );
    run("DELETE FROM visits WHERE day < ?", [
        date("Y-m-d", time() - 90 * 86400),
    ]);
}
function flash(string $message): void
{
    $_SESSION["flash"] = $message;
}

function article_order(array $f): string
{
    return (($f["sort"] ?? "latest") === "severity"
        ? "(SELECT MAX(c.cvss) FROM article_cves ac JOIN cves c ON c.id=ac.cve_id WHERE ac.article_id=a.id) DESC,"
        : "") . "a.published DESC,a.id DESC";
}

function base_path(): string {
    if (PHP_SAPI === 'cli') return '';
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return $path === '/' || $path === '.' ? '' : rtrim($path, '/');
}

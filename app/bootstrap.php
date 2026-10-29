<?php
declare(strict_types=1);
const ROOT = __DIR__ . "/..";
$localConfig = ROOT . '/config/local.php';
if (is_file($localConfig)) {
    foreach (require $localConfig as $name => $value) {
        if (is_string($name) && str_starts_with($name, 'ACTU_') && is_string($value) && (getenv($name) === false || getenv($name) === '')) putenv($name . '=' . $value);
    }
}
if (PHP_SAPI !== 'cli' && is_file(ROOT . '/config/install.enabled') && !is_file($localConfig)) {
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    header('Location: ' . ($base === '/' || $base === '.' ? '' : $base) . '/install.php', true, 303);
    exit;
}
date_default_timezone_set("Europe/Paris");
require_once __DIR__ . "/core.php";
require_once __DIR__ . "/feeds.php";
require_once __DIR__ . "/watch.php";
require_once __DIR__ . "/ai.php";
if (PHP_SAPI !== "cli") {
    ini_set("display_errors", "0");
    set_exception_handler(function (Throwable $error): void {
        error_log(
            "Actu: " .
                get_class($error) .
                " (configuration ou stockage à vérifier).",
        );
        http_response_code(500);
        header("Content-Type: text/html; charset=utf-8");
        echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Service indisponible</title><h1>Service temporairement indisponible</h1><p>Vérifiez la configuration et les droits du stockage.</p></html>';
    });
    session_name("actu_session");
    session_start([
        "cookie_httponly" => true,
        "cookie_path" => base_path() . "/",
        "cookie_secure" => is_https(),
        "cookie_samesite" => "Lax",
        "use_strict_mode" => true,
    ]);
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'self'",
    );
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Cache-Control: no-store");
    if (is_https()) {
        header("Strict-Transport-Security: max-age=31536000");
    }
    if (
        isset($_SESSION["admin_time"]) &&
        time() - (int) $_SESSION["admin_time"] > 1800
    ) {
        unset($_SESSION["admin_time"]);
    }
    if (is_admin()) {
        $_SESSION["admin_time"] = time();
    }
}

<?php
declare(strict_types=1);
const ROOT = __DIR__ . "/..";
date_default_timezone_set("Europe/Paris");
require_once __DIR__ . "/core.php";
require_once __DIR__ . "/feeds.php";
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

<?php
// Local-only mock upstream for the HTTP collector integration tests.
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($path === "/private") {
    header("Location: http://127.0.0.1/internal");
    exit();
}
if ($path === "/broken") {
    echo "<broken>";
    exit();
}
if ($path === "/fail") {
    http_response_code(503);
    echo "Unavailable";
    exit();
}
if ($path === "/large") {
    echo str_repeat("x", 9 * 1024 * 1024);
    exit();
}
if ($path === "/redirect") {
    header("Location: /rss");
    exit();
}
header('ETag: "fixture-v1"');
header("Last-Modified: Wed, 28 Oct 2026 12:00:00 GMT");
if (($_SERVER["HTTP_IF_NONE_MATCH"] ?? "") === '"fixture-v1"') {
    http_response_code(304);
    exit();
}
header("Content-Type: application/rss+xml");
readfile(__DIR__ . "/fixtures/rss.xml");

<?php

declare(strict_types=1);

// load functions
require_once dirname(__DIR__, 1) . "/vendor/autoload.php";
require_once "stats.php";
require_once "card.php";
require_once "cache.php";
require_once "generator.php";

// load .env
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->safeLoad();

// GitHub buckets commits by the commit timestamp's local offset, so "today"
// must follow the profile owner's timezone (UTC+8), not the server's UTC.
// Don't read $_ENV["TZ"]: Vercel sets it to ":UTC", which PHP rejects with a
// notice that starts output and kills every later header() call.
if (!@date_default_timezone_set($_ENV["CARD_TZ"] ?? "Asia/Taipei")) {
    date_default_timezone_set("UTC");
}

// if environment variables are not loaded, display error
if (!isset($_ENV["TOKEN"])) {
    $message = file_exists(dirname(__DIR__, 1) . "/.env")
        ? "Missing token in config. Check Contributing.md for details."
        : ".env was not found. Check Contributing.md for details.";
    renderOutput($message, 500);
}

// short client/proxy cache for freshness; edge serves stale instantly while
// revalidating in the background so camo never hits its ~4s fetch timeout
$cacheSeconds = 300;
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $cacheSeconds) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: public, max-age=$cacheSeconds, s-maxage=$cacheSeconds, stale-while-revalidate=86400");

// redirect to demo site if user is not given
if (!isset($_REQUEST["user"])) {
    header("Location: demo/");
    exit();
}

try {
    $stats = generateStreakStats($_REQUEST["user"], $_REQUEST);
    renderOutput($stats);
} catch (InvalidArgumentException | AssertionError $error) {
    error_log("Error {$error->getCode()}: {$error->getMessage()}");
    if ($error->getCode() >= 500) {
        error_log($error->getTraceAsString());
    }
    renderOutput($error->getMessage(), $error->getCode());
}

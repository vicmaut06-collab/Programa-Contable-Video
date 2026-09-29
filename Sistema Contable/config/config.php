<?php
declare(strict_types=1);

define('DB_DRIVER', 'pgsql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5433');
define('DB_NAME', 'contabilidad');
define('DB_USER', 'postgres');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8');

define('APP_NAME', 'Sistema Contable');
define('MONEDA', '$');
define('MAX_LINEAS', 50);
define('ASSET_VERSION', '1');

date_default_timezone_set('America/El_Salvador');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$__appDir = str_replace('\\', '/', dirname(__DIR__));
$__docRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
if ($__docRoot !== '' && stripos($__appDir, $__docRoot) === 0) {
    $__base = substr($__appDir, strlen($__docRoot));
} else {
    $__base = '/' . basename($__appDir);
}
define('BASE_URL', rtrim($__base, '/'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET client_encoding TO 'UTF8'");
    }
    return $pdo;
}

function dbSinBase(): PDO
{
    $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=postgres';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

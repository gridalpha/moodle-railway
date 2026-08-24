<?php
/**
 * Health endpoint for Railway.
 *
 * Deliberately does not bootstrap Moodle: this has to answer while the site is
 * under maintenance or mid-upgrade. It checks the one dependency whose loss is
 * invisible from the container — that Moodle's own database is reachable with
 * Moodle's own credentials and already holds its schema.
 */

header('Content-Type: text/plain; charset=utf-8');

function conninfo(string $key, string $value): string {
    return $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
}

$prefix = (string)(getenv('DB_PREFIX') ?: 'mdl_');
if (preg_match('/[^A-Za-z0-9_]/', $prefix)) {
    http_response_code(503);
    echo "invalid DB_PREFIX\n";
    exit;
}

$dsn = implode(' ', [
    conninfo('host', (string)getenv('DB_HOST')),
    conninfo('port', (string)(getenv('DB_PORT') ?: '5432')),
    conninfo('dbname', (string)(getenv('DB_NAME') ?: 'moodle')),
    conninfo('user', (string)(getenv('DB_USER') ?: 'moodle')),
    conninfo('password', (string)getenv('DB_PASS')),
    'connect_timeout=5',
]);

$conn = @pg_connect($dsn);
if ($conn === false) {
    http_response_code(503);
    echo "database unreachable\n";
    exit;
}

$res = @pg_query($conn, 'SELECT 1 FROM ' . $prefix . 'config LIMIT 1');
if ($res === false) {
    http_response_code(503);
    echo "moodle schema not readable\n";
    pg_close($conn);
    exit;
}

pg_close($conn);
echo "ok\n";

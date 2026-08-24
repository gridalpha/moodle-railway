<?php
/**
 * Create Moodle's own Postgres role and database using the managed superuser
 * credential, then step out of the way. Idempotent: safe on every boot.
 */

function out(string $msg): void {
    fwrite(STDOUT, "railway-db: {$msg}\n");
}

function fail(string $msg): void {
    fwrite(STDERR, "railway-db: {$msg}\n");
    exit(1);
}

$adminurl = (string)getenv('DB_BOOTSTRAP_URL');
$dbname   = (string)(getenv('DB_NAME') ?: 'moodle');
$dbuser   = (string)(getenv('DB_USER') ?: 'moodle');
$dbpass   = (string)getenv('DB_PASS');

if ($adminurl === '') {
    exit(0);
}
if ($dbpass === '') {
    fail('DB_PASS is empty; refusing to create a passwordless role.');
}

// No service ordering on Railway, so the database may still be starting.
$conn = false;
for ($i = 1; $i <= 30; $i++) {
    $conn = @pg_connect($adminurl . ' connect_timeout=5');
    if ($conn !== false) {
        break;
    }
    out("waiting for Postgres ({$i}/30)");
    sleep(5);
}
if ($conn === false) {
    fail('could not reach Postgres with DB_BOOTSTRAP_URL.');
}

function q($conn, string $sql) {
    $res = @pg_query($conn, $sql);
    if ($res === false) {
        fail('query failed: ' . trim(pg_last_error($conn)));
    }
    return $res;
}

$quser = pg_escape_identifier($conn, $dbuser);
$qdb   = pg_escape_identifier($conn, $dbname);
$lpass = pg_escape_literal($conn, $dbpass);

// Role. ALTER on every boot keeps the role's password in step with DB_PASS,
// so rotating the variable is all a redeploy needs.
$res = q($conn, "SELECT 1 FROM pg_roles WHERE rolname = " . pg_escape_literal($conn, $dbuser));
if (pg_num_rows($res) === 0) {
    q($conn, "CREATE ROLE {$quser} WITH LOGIN PASSWORD {$lpass}");
    out("created role {$dbuser}");
} else {
    q($conn, "ALTER ROLE {$quser} WITH LOGIN PASSWORD {$lpass}");
    out("role {$dbuser} already exists; password synchronised");
}

// Database, owned by that role.
$res = q($conn, "SELECT 1 FROM pg_database WHERE datname = " . pg_escape_literal($conn, $dbname));
if (pg_num_rows($res) === 0) {
    q($conn, "CREATE DATABASE {$qdb} OWNER {$quser}");
    out("created database {$dbname} owned by {$dbuser}");
} else {
    out("database {$dbname} already exists");
}

// Nothing else on this server should be reachable by the new role. PUBLIC holds
// CONNECT on every database by default, which is the only grant it would need.
$res = q($conn, "SELECT datname FROM pg_database WHERE datistemplate = false AND datname <> " . pg_escape_literal($conn, $dbname));
while ($row = pg_fetch_assoc($res)) {
    $other = pg_escape_identifier($conn, $row['datname']);
    q($conn, "REVOKE CONNECT ON DATABASE {$other} FROM PUBLIC");
    q($conn, "REVOKE ALL ON DATABASE {$other} FROM {$quser}");
}
out('revoked PUBLIC connect on every other database');

pg_close($conn);
exit(0);

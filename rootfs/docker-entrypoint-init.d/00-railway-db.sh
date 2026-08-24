#!/bin/sh
# Provision a scoped Postgres role and database for Moodle.
#
# Railway's managed Postgres hands out the superuser on the shared `railway`
# database. Moodle is plugin-extensible and every plugin runs SQL as whatever
# role Moodle was configured with, so it gets its own owner role on its own
# database instead. This runs before the image configures Moodle, is idempotent,
# and no-ops when DB_BOOTSTRAP_URL is unset (bring-your-own-database).
set -e

if [ -z "${DB_BOOTSTRAP_URL:-}" ]; then
    exit 0
fi

exec /usr/bin/php /usr/local/lib/railway-bootstrap-db.php

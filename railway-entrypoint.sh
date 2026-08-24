#!/bin/sh
# Railway mounts volumes root-owned, and the upstream image runs as `nobody`.
# Take ownership here, as root, then hand the image's own entrypoint straight
# back to `nobody` so nginx, php-fpm and Moodle cron never run privileged.
set -e

DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"

mkdir -p "$DATAROOT"
chown nobody:nobody "$DATAROOT"

# One recursive pass the first time a volume is used. Everything written after
# that is created by `nobody` already, so this never runs again and a large
# moodledata does not slow later deploys.
if [ ! -e "$DATAROOT/.railway-ownership" ]; then
    echo "railway: taking ownership of $DATAROOT (first boot on this volume)"
    chown -R nobody:nobody "$DATAROOT"
    su-exec nobody:nobody touch "$DATAROOT/.railway-ownership"
fi

exec su-exec nobody:nobody /bin/docker-entrypoint.sh "$@"

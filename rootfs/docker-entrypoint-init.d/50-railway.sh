#!/bin/sh
# Runs last, after the image has installed or upgraded Moodle and rewritten
# config.php. Everything here is idempotent and applies only to Railway.
set -e

CONFIG=/var/www/html/config.php
[ -f "$CONFIG" ] || exit 0

add_cfg() {
    key="$1"
    value="$2"
    if grep -q "CFG->${key}[^a-z]" "$CONFIG"; then
        return 0
    fi
    chmod u+w "$CONFIG" 2>/dev/null || true
    sed -i "/require_once/i \$CFG->${key} = ${value};" "$CONFIG"
    echo "railway: set \$CFG->${key}"
}

# Client IP behind Railway's edge.
#
# The edge overwrites any X-Forwarded-For the client supplied and appends its
# own hop, so the header always reads "<real client>, <railway edge>". Moodle
# reads the *rightmost* entry that is not listed in reverseproxyignore, which
# makes that list — not a trusted-proxy list — the correct knob here. The edge
# answers from the public 152.233.0.0/17 range and rotates within it per
# request, reaches containers over 100.64.0.0/10, and the private overlay is
# fd00::/8; with all three ignored the last survivor is the real client.
#
# getremoteaddrconf=1 is GETREMOTEADDR_SKIP_HTTP_CLIENT_IP alone: Client-IP is
# a header Railway never sets and any client can forge, so it stays skipped,
# while X-Forwarded-For becomes trusted.
add_cfg getremoteaddrconf "1"
add_cfg reverseproxyignore "'152.233.0.0/17,100.64.0.0/10,fd00::/8'"

chmod 0444 "$CONFIG" 2>/dev/null || true
exit 0

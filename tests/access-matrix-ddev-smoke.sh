#!/usr/bin/env bash
set -euo pipefail

base_url="${ADP_BASE_URL:-https://nextcloud-dev.ddev.site}"
ddev_project="${ADP_DDEV_PROJECT:-$(cd "$(dirname "$0")/../../nextcloud-dev" && pwd)}"
suffix="$(date +%s)-$$"
team_code="Smoke$$"
team_group="ad-ASN-$team_code"
created_users=()

occ() {
    (cd "$ddev_project" && ddev exec -d /var/www/html/html php occ "$@")
}

cleanup() {
    local uid
    for uid in "${created_users[@]}"; do
        occ user:delete "$uid" >/dev/null 2>&1 || true
    done
    occ group:delete "$team_group" >/dev/null 2>&1 || true
}
trap cleanup EXIT

create_user() {
    local uid="$1"
    (cd "$ddev_project" && ddev exec -d /var/www/html/html env OC_PASS="$uid" php occ user:add --password-from-env "$uid") >/dev/null
    created_users+=("$uid")
    occ group:adduser "$team_group" "$uid" >/dev/null
}

occ group:add "$team_group" >/dev/null
actor="adp-smoke-${suffix}-actor"
foreign="adp-smoke-${suffix}-foreign"
create_user "$actor"
create_user "$foreign"

ADP_BASE_URL="$base_url" ADP_USER="$actor" ADP_PASSWORD="$actor" \
    ADP_TEAM_CODE="$team_code" ADP_FOREIGN_UID="$foreign" \
    "$(dirname "$0")/access-http-smoke.sh"

echo 'AdPlaner DDEV access matrix smoke: OK'

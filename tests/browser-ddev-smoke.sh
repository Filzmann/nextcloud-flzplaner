#!/usr/bin/env bash
set -euo pipefail

base_url="${ADP_BASE_URL:-https://nextcloud-dev.ddev.site}"
ddev_project="${ADP_DDEV_PROJECT:-$(cd "$(dirname "$0")/../../nextcloud-dev" && pwd)}"
suffix="$(date +%s)$$"
team_code="P${suffix: -15}"
team_group="ad-ASN-$team_code"
month='2098-11'
actor="adp-browser-$suffix-eb"
assistant="adp-browser-$suffix-assistant"
member="adp-browser-$suffix-member"
created_users=()
workdir="$(mktemp -d)"
chrome_profile="$workdir/chrome-profile"
chrome_log="$workdir/chrome.log"
screenshot="$workdir/adplaner-approved.png"
chrome_pid=''
probe='/var/www/html/html/custom_apps/adplaner/tests/integration/PrivacyRuntimeProbe.php'

occ() {
    (cd "$ddev_project" && ddev exec -d /var/www/html/html php occ "$@")
}

run_probe() {
    (cd "$ddev_project" && ddev exec -d /var/www/html/html php "$probe" "$@")
}

cleanup() {
    local failed=0
    if [[ -n "$chrome_pid" ]] && kill -0 "$chrome_pid" >/dev/null 2>&1; then
        kill "$chrome_pid" >/dev/null 2>&1 || failed=1
        wait "$chrome_pid" >/dev/null 2>&1 || true
    fi
    run_probe cleanup "$team_code" "$month" >/dev/null || failed=1
    local uid
    for uid in "${created_users[@]}"; do
        occ user:delete "$uid" >/dev/null 2>&1 || failed=1
    done
    occ group:delete "$team_group" >/dev/null 2>&1 || failed=1
    rm -rf "$workdir"
    return "$failed"
}

report_failed_cleanup() {
    echo 'Der Browser-Smoke wurde abgebrochen; die automatische Bereinigung war nicht vollständig erfolgreich.' >&2
    echo "Bitte synthetische Objekte prüfen: Team $team_code, Gruppe $team_group, Nutzer ${created_users[*]:-keine}." >&2
}

trap 'cleanup || report_failed_cleanup' EXIT

create_user() {
    local uid="$1"
    (cd "$ddev_project" && ddev exec -d /var/www/html/html env OC_PASS="$uid" php occ user:add --password-from-env "$uid") >/dev/null
    created_users+=("$uid")
    occ group:adduser "$team_group" "$uid" >/dev/null
}

if ! occ group:info ad-EB >/dev/null 2>&1; then
    echo 'Die bestehende EB-Rollengruppe ad-EB fehlt; der Smoke verändert die Organisationskonfiguration nicht.' >&2
    exit 1
fi

occ group:add "$team_group" >/dev/null
create_user "$actor"
create_user "$assistant"
create_user "$member"
occ group:adduser ad-EB "$actor" >/dev/null

mkdir -p "$chrome_profile"
google-chrome \
    --headless=new \
    --disable-dev-shm-usage \
    --ignore-certificate-errors \
    --remote-allow-origins='*' \
    --remote-debugging-port=0 \
    --user-data-dir="$chrome_profile" \
    about:blank >"$chrome_log" 2>&1 &
chrome_pid="$!"

for _ in $(seq 1 100); do
    [[ -s "$chrome_profile/DevToolsActivePort" ]] && break
    if ! kill -0 "$chrome_pid" >/dev/null 2>&1; then
        echo 'Headless Chrome wurde unerwartet beendet.' >&2
        sed -n '1,120p' "$chrome_log" >&2
        exit 1
    fi
    sleep 0.1
done
if [[ ! -s "$chrome_profile/DevToolsActivePort" ]]; then
    echo 'Chrome stellte innerhalb des Zeitlimits keinen DevTools-Port bereit.' >&2
    exit 1
fi
cdp_port="$(sed -n '1p' "$chrome_profile/DevToolsActivePort")"

ADP_CDP_PORT="$cdp_port" \
ADP_BASE_URL="$base_url" \
ADP_BROWSER_EB="$actor" \
ADP_BROWSER_ASSISTANT="$assistant" \
ADP_BROWSER_MEMBER="$member" \
ADP_BROWSER_TEAM="$team_code" \
ADP_BROWSER_MONTH="$month" \
ADP_BROWSER_SCREENSHOT="$screenshot" \
node "$(dirname "$0")/js/browser-ddev-smoke.mjs"

if [[ ! -s "$screenshot" ]]; then
    echo 'Der genehmigte Plan wurde nicht als temporärer Sichtnachweis erfasst.' >&2
    exit 1
fi

cleanup
trap - EXIT
run_probe assert-clean "$team_code" "$month" >/dev/null
for uid in "$actor" "$assistant" "$member"; do
    if occ user:info "$uid" >/dev/null 2>&1; then
        echo "Synthetischer Nutzer verblieb nach der Bereinigung: $uid" >&2
        exit 1
    fi
done
if occ group:info "$team_group" >/dev/null 2>&1; then
    echo "Synthetische Gruppe verblieb nach der Bereinigung: $team_group" >&2
    exit 1
fi

echo 'AdPlaner selbstbereinigende Browser-Abnahme: OK'

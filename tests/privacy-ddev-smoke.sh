#!/usr/bin/env bash
set -euo pipefail

base_url="${FLZP_BASE_URL:-https://nextcloud-dev.ddev.site}"
ddev_project="${FLZP_DDEV_PROJECT:-$(cd "$(dirname "$0")/../../nextcloud-dev" && pwd)}"
suffix="$(date +%s)$$"
team_code="P${suffix: -15}"
team_group="flz-ASN-$team_code"
month='2098-11'
work_date="$month-03"
actor="flz-planer-privacy-$suffix-eb"
active="flz-planer-privacy-$suffix-active"
disabled="flz-planer-privacy-$suffix-disabled"
created_users=()
workdir="$(mktemp -d)"
probe='/var/www/html/html/custom_apps/flzplaner/tests/integration/PrivacyRuntimeProbe.php'

occ() {
    (cd "$ddev_project" && ddev exec -d /var/www/html/html php occ "$@")
}

run_probe() {
    (cd "$ddev_project" && ddev exec -d /var/www/html/html php "$probe" "$@")
}

cleanup() {
    local failed=0
    run_probe cleanup "$team_code" "$month" || failed=1
    local uid
    for uid in "${created_users[@]}"; do
        occ user:delete "$uid" >/dev/null 2>&1 || failed=1
    done
    occ group:delete "$team_group" >/dev/null 2>&1 || failed=1
    rm -rf "$workdir"
    return "$failed"
}

report_failed_cleanup() {
    echo 'Der Datenschutz-Smoke wurde abgebrochen; die automatische Bereinigung war nicht vollständig erfolgreich.' >&2
    echo "Bitte synthetische Objekte prüfen: Team $team_code, Gruppe $team_group, Nutzer ${created_users[*]:-keine}." >&2
}

trap 'cleanup || report_failed_cleanup' EXIT

create_user() {
    local uid="$1"
    (cd "$ddev_project" && ddev exec -d /var/www/html/html env OC_PASS="$uid" php occ user:add --password-from-env "$uid") >/dev/null
    created_users+=("$uid")
    occ group:adduser "$team_group" "$uid" >/dev/null
}

if ! occ group:info flz-EB >/dev/null 2>&1; then
    echo 'Die bestehende EB-Rollengruppe flz-EB fehlt; der Smoke verändert die Organisationskonfiguration nicht.' >&2
    exit 1
fi

occ group:add "$team_group" >/dev/null
create_user "$actor"
create_user "$active"
create_user "$disabled"
occ group:adduser flz-EB "$actor" >/dev/null
occ user:disable "$disabled" >/dev/null

page="$workdir/page.html"
cookies="$workdir/cookies.txt"
plan="$workdir/plan.json"
response="$workdir/response.json"

curl --fail --silent --show-error --insecure --user "$actor:$actor" \
    --cookie-jar "$cookies" "$base_url/index.php/apps/flzplaner/" --output "$page"
token="$(sed -n 's/.*data-requesttoken="\([^"]*\)".*/\1/p' "$page" | head -n 1)"
if [[ -z "$token" ]]; then
    echo 'Request-Token fehlt.' >&2
    exit 1
fi

plan_endpoint="$base_url/index.php/apps/flzplaner/api/teams/$team_code/months/$month"
curl --fail --silent --show-error --insecure --user "$actor:$actor" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" \
    "$plan_endpoint" --output "$plan"

php -r '
$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$uids = array_column($data["team"]["assistants"] ?? [], "uid");
if (!in_array($argv[2], $uids, true)) {
    throw new RuntimeException("Die aktive Assistenz fehlt im öffentlichen Team-Payload.");
}
foreach ([$argv[3], $argv[4]] as $forbiddenUid) {
    if (in_array($forbiddenUid, $uids, true)) {
        throw new RuntimeException("Ein nicht schichtfähiges Konto wurde öffentlich als Assistenz angeboten: " . $forbiddenUid);
    }
}
' "$plan" "$active" "$disabled" "$actor"

before="$(run_probe snapshot "$team_code" "$month")"
curl --fail --silent --show-error --insecure --user "$actor:$actor" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" \
    "$plan_endpoint" --output "$plan"
after="$(run_probe snapshot "$team_code" "$month")"
php -r '
$before = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$after = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
if ($after["revision"] <= $before["revision"]) {
    throw new RuntimeException("Das technische Monats-Locking hat die Revision nicht fortgeschrieben.");
}
if ($after["updatedByUid"] !== $before["updatedByUid"] || $after["updatedAt"] !== $before["updatedAt"]) {
    throw new RuntimeException("Das technische Monats-Locking hat fachliche Änderungsmetadaten überschrieben.");
}
' "$before" "$after"

note_endpoint="$plan_endpoint/days/$work_date/note"
curl --fail --silent --show-error --insecure --user "$actor:$actor" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' \
    -X POST --data '{"note":"Synthetische Prüfbemerkung"}' "$note_endpoint" --output "$response"
run_probe assert-note-present "$team_code" "$month" "$work_date" >/dev/null

curl --fail --silent --show-error --insecure --user "$actor:$actor" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' \
    -X POST --data '{"note":"   "}' "$note_endpoint" --output "$response"
run_probe assert-note-absent "$team_code" "$month" "$work_date" >/dev/null

cleanup
trap - EXIT
run_probe assert-clean "$team_code" "$month" >/dev/null
for uid in "$actor" "$active" "$disabled"; do
    if occ user:info "$uid" >/dev/null 2>&1; then
        echo "Synthetischer Nutzer verblieb nach der Bereinigung: $uid" >&2
        exit 1
    fi
done
if occ group:info "$team_group" >/dev/null 2>&1; then
    echo "Synthetische Gruppe verblieb nach der Bereinigung: $team_group" >&2
    exit 1
fi

echo 'FlzPlaner datensparsamer DDEV-Runtime-Smoke: OK'

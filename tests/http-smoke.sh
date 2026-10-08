#!/usr/bin/env bash
set -euo pipefail

: "${FLZP_BASE_URL:?FLZP_BASE_URL fehlt}"
: "${FLZP_USER:?FLZP_USER fehlt}"
: "${FLZP_PASSWORD:?FLZP_PASSWORD fehlt}"

workdir="$(mktemp -d)"
page="$workdir/page.html"
cookies="$workdir/cookies.txt"
state="$workdir/state.json"
csrf_error="$workdir/csrf-error.json"
access_error="$workdir/access-error.json"
trap 'rm -rf "$workdir"' EXIT

curl --fail --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie-jar "$cookies" "$FLZP_BASE_URL/index.php/apps/flzplaner/" --output "$page"
for contract in 'id="flzplaner-app"' 'id="team-select"' 'id="month-input"' 'id="flz-planer-panel"'; do
    if ! grep -q "$contract" "$page"; then
        echo "App-DOM-Vertrag fehlt: $contract" >&2
        exit 1
    fi
done

token="$(sed -n 's/.*data-requesttoken="\([^"]*\)".*/\1/p' "$page" | head -n 1)"
if [[ -z "$token" ]]; then
    echo 'Request-Token fehlt.' >&2
    exit 1
fi

curl --fail --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" \
    "$FLZP_BASE_URL/index.php/apps/flzplaner/api/state" --output "$state"
FLZP_STATE="$state" FLZP_USER="$FLZP_USER" php -r '
$state = json_decode(file_get_contents(getenv("FLZP_STATE")), true, flags: JSON_THROW_ON_ERROR);
if (($state["currentUser"]["uid"] ?? "") !== getenv("FLZP_USER")) throw new RuntimeException("Aktuelles Konto fehlt im API-Zustand.");
if (!is_array($state["teams"] ?? null)) throw new RuntimeException("Teamliste fehlt im API-Zustand.");
if (!is_array($state["organization"] ?? null)) throw new RuntimeException("Organisationsvertrag fehlt im API-Zustand.");
'

endpoint="$FLZP_BASE_URL/index.php/apps/flzplaner/api/teams/SmokeMissing/settings"
status="$(curl --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie "$cookies" --cookie-jar "$cookies" -H 'Content-Type: application/json' \
    -X POST --data '{}' --write-out '%{http_code}' --output "$csrf_error" "$endpoint")"
if [[ "$status" != '412' ]]; then
    echo "Schreibzugriff ohne CSRF-Token ergab HTTP $status statt 412." >&2
    exit 1
fi

status="$(curl --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' \
    -X POST --data '{}' --write-out '%{http_code}' --output "$access_error" "$endpoint")"
if [[ "$status" != '403' ]] || ! php -r '$data=json_decode(file_get_contents($argv[1]),true); exit(($data["ok"] ?? true) === false ? 0 : 1);' "$access_error"; then
    echo "Unzulässiger Teamzugriff ergab keinen verständlichen HTTP-403-Fehler." >&2
    exit 1
fi

echo "FlzPlaner HTTP smoke: OK ($FLZP_USER)"

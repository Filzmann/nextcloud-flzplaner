#!/usr/bin/env bash
set -euo pipefail

: "${FLZP_BASE_URL:?FLZP_BASE_URL fehlt}"
: "${FLZP_USER:?FLZP_USER fehlt}"
: "${FLZP_PASSWORD:?FLZP_PASSWORD fehlt}"
: "${FLZP_TEAM_CODE:?FLZP_TEAM_CODE fehlt}"
: "${FLZP_FOREIGN_UID:?FLZP_FOREIGN_UID fehlt}"

workdir="$(mktemp -d)"
page="$workdir/page.html"
cookies="$workdir/cookies.txt"
plan="$workdir/plan.json"
error="$workdir/error.json"
plan_after="$workdir/plan-after.json"
trap 'rm -rf "$workdir"' EXIT

curl --fail --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie-jar "$cookies" "$FLZP_BASE_URL/index.php/apps/flzplaner/" --output "$page"

token="$(sed -n 's/.*data-requesttoken="\([^"]*\)".*/\1/p' "$page" | head -n 1)"
if [[ -z "$token" ]]; then
    echo 'Request-Token fehlt.' >&2
    exit 1
fi

month="$(date -u +%Y-%m)"
plan_endpoint="$FLZP_BASE_URL/index.php/apps/flzplaner/api/teams/$FLZP_TEAM_CODE/months/$month"
curl --fail --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" \
    "$plan_endpoint" --output "$plan"

slot_id="$(php -r '
$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
foreach (($data["days"] ?? []) as $day) {
    foreach (($day["slots"] ?? []) as $slot) {
        if (isset($slot["id"])) {
            echo (int)$slot["id"];
            exit;
        }
    }
}
exit(1);
' "$plan")"

candidate_endpoint="$plan_endpoint/slots/$slot_id/candidates"
status="$(curl --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" -H 'Content-Type: application/json' \
    -X POST --data "{\"targetUid\":\"$FLZP_FOREIGN_UID\"}" --write-out '%{http_code}' \
    --output "$error" "$candidate_endpoint")"
if [[ "$status" != '403' ]] || ! php -r '
$data = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
exit(($data["ok"] ?? true) === false ? 0 : 1);
' "$error"; then
    echo "Fremdänderung durch normales Teammitglied ergab HTTP $status statt eines verständlichen 403-Fehlers." >&2
    exit 1
fi

curl --fail --silent --show-error --insecure --user "$FLZP_USER:$FLZP_PASSWORD" \
    --cookie "$cookies" --cookie-jar "$cookies" -H "requesttoken: $token" \
    "$plan_endpoint" --output "$plan_after"
php -r '
$before = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$after = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
$slotId = (int)$argv[3];
$candidateUids = static function (array $plan, int $slotId): array {
    foreach (($plan["days"] ?? []) as $day) {
        foreach (($day["slots"] ?? []) as $slot) {
            if ((int)($slot["id"] ?? 0) !== $slotId) {
                continue;
            }
            $uids = array_map(static fn(array $candidate): string => (string)($candidate["uid"] ?? ""), $slot["candidates"] ?? []);
            sort($uids);
            return $uids;
        }
    }

    throw new RuntimeException("Die geprüfte Schicht fehlt im Monatsplan.");
};
$beforeCandidateUids = $candidateUids($before, $slotId);
$afterCandidateUids = $candidateUids($after, $slotId);
if ($beforeCandidateUids !== $afterCandidateUids) {
    throw new RuntimeException("Die abgewiesene Fremdänderung hat den Kandidatenzustand verändert.");
}
' "$plan" "$plan_after" "$slot_id"

echo "FlzPlaner C3 API access smoke: OK ($FLZP_USER)"

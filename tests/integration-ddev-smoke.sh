#!/usr/bin/env bash
set -euo pipefail

ddev_project="${ADP_DDEV_PROJECT:-$(cd "$(dirname "$0")/../../nextcloud-dev" && pwd)}"

(cd "$ddev_project" && ddev exec -d /var/www/html/html php -r \
    "define('OC_CONSOLE', true); require '/var/www/html/html/custom_apps/adplaner/tests/integration/MonthPlanStatusSmoke.php';")

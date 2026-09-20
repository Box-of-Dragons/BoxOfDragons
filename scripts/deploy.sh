#!/usr/bin/env bash
# BoxOfDragons post-reset deploy steps. Runs on the VPS after
# git fetch + reset --hard, invoked by the shared family-deploy workflow
# (and can also be run by hand during a manual deploy).
set -euo pipefail

echo "Generating build info..."
php scripts/GenerateBuildInfo.php --root=. --output=web/js/buildInfo.js --format=js
php scripts/GenerateBuildInfo.php --root=. --output=web/changelog.html --format=html

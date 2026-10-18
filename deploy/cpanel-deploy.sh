#!/usr/bin/env bash
# Install or update the app on the cPanel server (PHP 8.4, Node 22).
# Run from anywhere:  bash ~/odaamsterderm_web/deploy/cpanel-deploy.sh
set -euo pipefail

PHP=${PHP:-/opt/cpanel/ea-php84/root/usr/bin/php}
NODE_BIN=${NODE_BIN:-/opt/alt/alt-nodejs22/root/usr/bin}
COMPOSER=${COMPOSER:-$HOME/bin/composer}

cd "$(dirname "$0")/.."

git pull --ff-only
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
PATH="$NODE_BIN:$PATH" npm ci --no-audit --no-fund
PATH="$NODE_BIN:$PATH" npm run build
"$PHP" artisan migrate --force
"$PHP" artisan storage:link 2>/dev/null || true
"$PHP" artisan optimize:clear
"$PHP" artisan optimize

echo "Deployed $(git rev-parse --short HEAD)"

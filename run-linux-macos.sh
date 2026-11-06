#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
php -v
php -m | grep -qi pdo_sqlite || { echo "Enable pdo_sqlite first."; exit 1; }
php -m | grep -qi fileinfo || { echo "Enable fileinfo first."; exit 1; }
mkdir -p storage public/uploads
php -S 127.0.0.1:8000 -t public

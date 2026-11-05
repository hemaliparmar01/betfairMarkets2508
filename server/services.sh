#!/bin/sh
set -eu
SERVER_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
if [ "$(uname -s)" != "Linux" ]; then
    echo "Server services require the Linux host running systemd." >&2
    exit 1
fi
export SERVICES_ENV="${SERVICES_ENV:-production}"
export SERVICES_ROOT="${SERVICES_ROOT:-$(CDPATH= cd -- "$SERVER_DIR/.." && pwd)}"
export SERVICES_CONFIG="${SERVICES_CONFIG:-$SERVER_DIR/services-manager.json}"
export SERVICES_CONTAINER="${SERVICES_CONTAINER:-aapanel}"
export SERVICES_CONTAINER_ROOT="${SERVICES_CONTAINER_ROOT:-/www/wwwroot/staging777.bfmarketsanalytics.com}"
exec "${SERVICES_PYTHON:-python3}" "$SERVER_DIR/services-manager.py" "$@"

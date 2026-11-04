#!/bin/sh
set -eu
LOCAL_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
if [ "$(uname -s)" != "Darwin" ]; then
    echo "Local services are for macOS only. Use your server service scripts on the server." >&2
    exit 1
fi
export SERVICES_ENV=local
export SERVICES_ROOT="${SERVICES_ROOT:-$(CDPATH= cd -- "$LOCAL_ROOT/.." && pwd)}"
export SERVICES_CONFIG="${SERVICES_CONFIG:-$LOCAL_ROOT/services-manager.json}"
exec "${SERVICES_PYTHON:-python3}" "$LOCAL_ROOT/services-manager.py" "$@"

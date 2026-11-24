#!/bin/sh
set -eu
MAIN_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
MANAGER="$MAIN_DIR/services-manager.py"
if [ "$(uname -s)" != "Linux" ]; then
    echo "Production services require the native Linux host running systemd." >&2
    exit 1
fi
export SERVICES_ENV=production
export SERVICES_ROOT=/www/wwwroot/bfmarketsanalytics.com
export SERVICES_CONFIG="$MAIN_DIR/services-manager.json"
export SERVICES_PREFIX=bfmarkets-production
export SERVICES_PHP=/www/server/php/83/bin/php
export SERVICES_USER=www
export PHPRC=/etc/php/8.3/cli-custom/php.ini
export PYTHONDONTWRITEBYTECODE=1
unset SERVICES_CONTAINER SERVICES_CONTAINER_ROOT
PYTHON=${SERVICES_PYTHON:-python3}
ACTION=${1:-}
if [ "$#" -gt 0 ]; then
    shift
fi
case "$ACTION" in
    prepare|install|start|restart|reload|check)
        if [ ! -r "$PHPRC" ]; then
            echo "Missing readable production PHP CLI configuration: $PHPRC" >&2
            exit 1
        fi
        ;;
esac
case "$ACTION" in
    prepare|install|start|restart|reload)
        UNITS=$("$PYTHON" - "$MANAGER" "$@" <<'PY'
import runpy
import sys
if len(sys.argv) > 3:
    raise SystemExit('Pass at most one service name.')
manager = runpy.run_path(sys.argv[1])['Manager']()
for spec in manager.selected(sys.argv[2] if len(sys.argv) == 3 else None).values():
    print(spec['unit'])
PY
        )
        if [ "$ACTION" != prepare ]; then
            for UNIT in $UNITS; do
                DROP_IN="/etc/systemd/system/$UNIT.d/10-production-php.conf"
                if [ -e "$DROP_IN" ] && ! cmp -s "$MAIN_DIR/php-cli.conf" "$DROP_IN"; then
                    echo "Production PHP drop-in differs; review manually: $DROP_IN" >&2
                    exit 1
                fi
                if [ "$ACTION" != install ] && [ ! -f "$DROP_IN" ]; then
                    echo "Missing production PHP drop-in; run prepare and install first: $UNIT" >&2
                    exit 1
                fi
            done
        fi
        "$PYTHON" "$MANAGER" "$ACTION" "$@"
        case "$ACTION" in
            prepare|install)
                for UNIT in $UNITS; do
                    if [ "$ACTION" = prepare ]; then
                        DESTINATION="$SERVICES_ROOT/storage/app/service-manager/$UNIT.d"
                    else
                        DESTINATION="/etc/systemd/system/$UNIT.d"
                    fi
                    mkdir -p "$DESTINATION"
                    cp "$MAIN_DIR/php-cli.conf" "$DESTINATION/10-production-php.conf"
                    chmod 0644 "$DESTINATION/10-production-php.conf"
                    echo "$DESTINATION/10-production-php.conf"
                done
                if [ "$ACTION" = install ]; then
                    systemctl daemon-reload
                fi
                ;;
        esac
        ;;
    *) exec "$PYTHON" "$MANAGER" "$ACTION" "$@" ;;
esac

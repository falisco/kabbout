#!/bin/sh
# Settings that cannot work stop the start here, with one line that says why.
set -e
php bin/console kabbout:check --no-ansi
# What is kept outlives the container: what has expired since the last start (guests nobody saw for a
# month, yesterday's counts) is removed from the disk here.
php bin/console cache:pool:prune --quiet

# The address to open is printed once the server answers (it gives up after a minute). The bridge is healthy
# by then: the compose file starts this container after it. Nothing is waited for when another command is run.
if [ "$1" = frankenphp ]; then
  (
    tries=0
    until curl -fsS -o /dev/null http://127.0.0.1:8080/api/capabilities 2>/dev/null; do
      tries=$((tries + 1))
      [ "$tries" -lt 120 ] || exit 0
      sleep 0.5
    done
    echo "Kabbout is ready: http://localhost:${KABBOUT_PORT:-8080}"
  ) &
fi

exec "$@"

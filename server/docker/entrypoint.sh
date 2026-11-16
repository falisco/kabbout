#!/bin/sh
# Settings that cannot work stop the start here, with one line that says why.
set -e
php bin/console kabbout:check --no-ansi

# The address to open is printed once the server answers. The bridge is healthy by then:
# the compose file starts this container after it.
(
  until curl -fsS -o /dev/null http://127.0.0.1:8080/api/capabilities 2>/dev/null; do sleep 0.5; done
  echo "Kabbout is ready: http://localhost:${KABBOUT_PORT:-8080}"
) &

exec "$@"

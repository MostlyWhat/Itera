#!/usr/bin/env bash
#
# Wait until the new Wasmer Edge deployment answers the health check.
#
# `wasmer deploy` waits on the custom domain, which goes through Cloudflare.
# From a GitHub runner that wait can time out although the deployment works.
# The workflow deploys with --no-wait and runs this script instead. It checks
# the wasmer.app domain directly.
#
# Usage:
#   wait-for-deploy.sh record     Print the live version id (before the deploy).
#   wait-for-deploy.sh wait OLD   Wait until /up returns 200 from a version
#                                 that is not OLD (after the deploy).

set -euo pipefail

URL="${WASMER_HEALTH_URL:-https://itera.wasmer.app/up}"
ATTEMPTS="${WASMER_HEALTH_ATTEMPTS:-30}"
DELAY="${WASMER_HEALTH_DELAY:-10}"

# Print "<status> <version id>" for one request to the health check.
probe() {
    curl --silent --show-error --max-time 15 --output /dev/null --dump-header - "$URL" 2>/dev/null \
        | tr -d '\r' \
        | awk 'NR == 1 { status = $2 } tolower($1) == "x-edge-app-version-id:" { version = $2 } END { print status, version }' \
        || echo "000 "
}

case "${1:-}" in
    record)
        read -r _ version <<< "$(probe)"
        echo "${version:-none}"
        ;;
    wait)
        old="${2:-none}"

        for attempt in $(seq 1 "$ATTEMPTS"); do
            read -r status version <<< "$(probe)"

            if [[ "$status" == "200" && -n "${version:-}" && "$version" != "$old" ]]; then
                echo "Deployment $version is live (attempt $attempt)."
                exit 0
            fi

            echo "Attempt $attempt of $ATTEMPTS: status ${status:-none}, version ${version:-none}. Waiting ${DELAY}s."
            sleep "$DELAY"
        done

        echo "The new deployment did not answer $URL after $ATTEMPTS attempts." >&2
        exit 1
        ;;
    *)
        echo "Usage: $0 record | wait OLD_VERSION" >&2
        exit 2
        ;;
esac

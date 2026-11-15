#!/usr/bin/env bash
#
# Run the Laravel migrations against the Wasmer Edge PostgreSQL database.
#
# The script reads the database credentials from the Wasmer app secrets.
# It needs native PHP with pdo_pgsql, the Wasmer CLI, and a Wasmer login
# (or the WASMER_TOKEN environment variable).
#
# Run it from the application root (the directory that contains artisan).
# It never runs migrate:fresh, migrate:reset, or seeders.

set -euo pipefail

APP="${WASMER_APP:-mostlywhat/itera}"

for name in DB_HOST DB_PORT DB_NAME DB_USERNAME DB_PASSWORD; do
    value="$(wasmer app secret reveal "$name" --app "$APP")"

    if [[ -z "$value" ]]; then
        echo "The Wasmer secret $name is empty or missing for $APP." >&2
        exit 1
    fi

    # Hide the value in the GitHub Actions log.
    if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
        echo "::add-mask::$value"
    fi

    export "$name=$value"
done

export DB_CONNECTION=pgsql
export APP_ENV=production

php artisan migrate --force --no-interaction

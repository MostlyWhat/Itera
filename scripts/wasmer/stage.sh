#!/usr/bin/env bash
#
# Copy the built Laravel application into ./deploy for the Wasmer package.
#
# Run this script from the repository root after these commands:
#   composer install --no-dev, composer run build:production, pnpm run build
#
# The script copies only the files that Laravel needs at runtime.
# It never copies .env files, tests, node_modules, logs, or SQLite databases.

set -euo pipefail

STAGE_DIR="deploy"

for required in vendor/autoload.php public/build/manifest.json bootstrap/cache/packages.php; do
    if [[ ! -f "$required" ]]; then
        echo "Missing $required. Build the application before you stage it." >&2
        exit 1
    fi
done

if [[ -d vendor/laravel/boost || -d vendor/pestphp ]]; then
    echo "The vendor directory contains development packages. Run 'composer install --no-dev'." >&2
    exit 1
fi

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

# Application source, configuration, routes, and production dependencies.
cp -R app config routes vendor "$STAGE_DIR/"
cp artisan composer.json "$STAGE_DIR/"

# Bootstrap files and the package discovery cache from the --no-dev install.
mkdir -p "$STAGE_DIR/bootstrap/cache"
cp bootstrap/app.php bootstrap/providers.php "$STAGE_DIR/bootstrap/"
cp bootstrap/cache/packages.php bootstrap/cache/services.php "$STAGE_DIR/bootstrap/cache/"

# Migrations, seeders, and factories. Composer autoloads the seeders and factories.
mkdir -p "$STAGE_DIR/database"
cp -R database/migrations database/seeders database/factories "$STAGE_DIR/database/"

# Blade views. The compiled Svelte pages are in public/build.
mkdir -p "$STAGE_DIR/resources"
cp -R resources/views "$STAGE_DIR/resources/"

# Public files and compiled Vite assets, without development files.
cp -R public "$STAGE_DIR/"
rm -rf "$STAGE_DIR/public/hot" "$STAGE_DIR/public/storage" "$STAGE_DIR/public/fonts-manifest.dev.json"

# Router for the PHP built-in web server.
cp scripts/wasmer/router.php "$STAGE_DIR/router.php"

# Empty writable storage structure.
for directory in app/private app/public framework/cache/data framework/sessions framework/views logs; do
    mkdir -p "$STAGE_DIR/storage/$directory"
done

# Remove files that must never go into the package.
find "$STAGE_DIR" \( -name '.env' -o -name '.env.*' -o -name '*.sqlite' -o -name '*.log' \) -type f -delete

test -f "$STAGE_DIR/vendor/autoload.php"
test -f "$STAGE_DIR/public/build/manifest.json"

echo "Staged the application in $STAGE_DIR ($(du -sh "$STAGE_DIR" | cut -f1))."

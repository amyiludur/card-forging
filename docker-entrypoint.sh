#!/usr/bin/env bash
# Prepares the app on every start. Everything here is idempotent, so the
# container can be rebuilt and restarted without losing the designer's work.
set -euo pipefail

cd /app

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force --ansi

# The database lives in a bind-mounted directory, so it survives the container.
DB="${DB_DATABASE:-/app/database/database.sqlite}"
mkdir -p "$(dirname "$DB")"
[ -f "$DB" ] || touch "$DB"

php artisan migrate --force --ansi

# Import design/ once, on a fresh database. Importing on every boot would
# overwrite edits that have not been exported yet. To reload the design folder
# by hand: docker compose exec app php artisan design:import
SEEDED="$(dirname "$DB")/.design-imported"

if [ ! -f "$SEEDED" ]; then
    php artisan design:import --ansi
    touch "$SEEDED"
fi

exec "$@"

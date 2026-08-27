#!/bin/sh
set -e
cd /app

# APP_KEY: usa quella passata via ambiente, altrimenti generane una e conservala
# nel volume persistente (storage) così resta stabile tra i riavvii.
if [ -z "$APP_KEY" ]; then
  if [ ! -f storage/app_key ]; then
    php artisan key:generate --show > storage/app_key
  fi
  APP_KEY="$(cat storage/app_key)"
  export APP_KEY
fi

# Attende il database
echo "In attesa del database ${DB_HOST}:${DB_PORT}..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  sleep 2
done
echo "Database raggiungibile."

# Migrazioni + utenti iniziali (idempotente)
php artisan migrate --force --seed

# Cache di configurazione/rotte per la produzione
php artisan config:cache
php artisan route:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"

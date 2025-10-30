#!/usr/bin/env bash
set -e

# Attendre la base au boot (facultatif, on réessaye plusieurs fois)
tries=0
max=30
until php -r 'exit((int)!@mysqli_connect(getenv("DB_HOST"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"))===NULL);'; do
  tries=$((tries+1))
  if [ "$tries" -ge "$max" ]; then
    echo "DB non joignable, on continue quand même (Railway peut mettre un peu de temps)."
    break
  fi
  echo "⏳ Attente DB ($tries/$max) ..."
  sleep 2
done

# Clés / caches (ne casse pas si APP_KEY existe déjà)
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Si APP_KEY vide, on n'essaie pas de la générer (tu la poses côté Railway)
# Sinon Laravel plantera au boot → mieux vaut poser APP_KEY côté UI Railway
# php artisan key:generate --force # ← volontairement désactivé

# Migrations + storage:link (idempotent)
php artisan migrate --force || true
php artisan storage:link || true

# Caches prod
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Lancer Apache
exec apache2-foreground

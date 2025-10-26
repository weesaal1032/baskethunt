#!/usr/bin/env bash
set -euo pipefail

if [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

php artisan test --pest

./vendor/bin/phpstan analyse --configuration=phpstan.neon.dist

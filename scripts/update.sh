# Mise à jour du projet et du cache Symfony
set -e
cd "$(dirname "$0")/.."
if [ -d .git ]; then
  git pull
fi
composer install
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

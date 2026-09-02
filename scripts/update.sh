#!/bin/bash



set -e

cd /var/www/api.clashboard

git pull

composer install

echo "Audit dependances"
composer audit

echo "Verif dependances a MAJ"
composer outdated --direct || true

echo "Tests unitaires"
php bin/phpunit --testdox

echo "Analyse statique"
vendor/bin/phpstan analyse --no-progress

echo "Reload cache Symfony"
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

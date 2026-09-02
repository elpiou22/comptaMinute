# Exécution des tests PHPUnit
set -e
cd "$(dirname "$0")/.."
php bin/phpunit --testdox

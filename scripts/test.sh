# Exécution des tests PHPUnit
set -e
cd "$(dirname "$0")/.."
vendor/bin/phpunit --testdox

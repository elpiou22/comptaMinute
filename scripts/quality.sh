# Analyse de qualité du code avec PHPStan
set -e
cd "$(dirname "$0")/.."
vendor/bin/phpstan analyse --no-progress --memory-limit=512M

# Vérification de la sécurité des dépendances
set -e
cd "$(dirname "$0")/.."
composer audit

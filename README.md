# ComptaMinute - API Symfony

API Symfony utilisée par l'application Android ComptaMinute.

## Installation

```bash
git clone https://github.com/elpiou22/comptaMinute.git
cd comptaMinute
cp .env.example .env
bash scripts/install.sh
```

Le fichier `.env` doit être complété sur le serveur avec les vraies valeurs de `APP_SECRET`, `API_KEY`, `USER_KEYS` et `PHOTOS_DIR`.

## Scripts

```bash
bash scripts/install.sh
bash scripts/update.sh
bash scripts/test.sh
bash scripts/quality.sh
bash scripts/security.sh
```
